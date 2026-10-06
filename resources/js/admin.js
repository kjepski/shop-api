import Alpine from 'alpinejs';

/**
 * Admin panel that talks only to the public REST API (/api/*) with a Bearer token,
 * so everything it shows is exactly what the API returns. Permissions are enforced
 * by the API; hiding buttons for non-admins is only a convenience.
 */

const TOKEN_KEY = 'shop-admin-token';

class ApiError extends Error {
    constructor(status, body) {
        super(body?.message || `Błąd HTTP ${status}`);
        this.status = status;
        this.errors = body?.errors || {};
    }
}

/** "49,99" or "49.99" -> 4999 grosze, without going through floats. Returns null for invalid input. */
function zlotyToGrosze(value) {
    const match = String(value).trim().match(/^(\d+)(?:[.,](\d{1,2}))?$/);
    if (!match) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] || '0').padEnd(2, '0'));
}

function groszeToZloty(grosze) {
    return `${Math.floor(grosze / 100)},${String(grosze % 100).padStart(2, '0')}`;
}

function readToken() {
    try {
        return sessionStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

function writeToken(token) {
    try {
        token ? sessionStorage.setItem(TOKEN_KEY, token) : sessionStorage.removeItem(TOKEN_KEY);
    } catch {
        // Storage can be blocked; the panel then just needs a new login after a reload.
    }
}

const emptyProduct = () => ({
    name: '', slug: '', sku: '', price: '', stock: 0, description: '', is_active: true, category_id: '',
});

const emptyCategory = () => ({ name: '', slug: '', parent_id: '' });

const RESOURCES = { product: '/products', category: '/categories', user: '/users' };

Alpine.data('adminPanel', () => ({
    token: readToken(),
    user: null,
    tab: 'products',
    productsLoading: false,
    categoriesLoading: false,
    flash: null,

    // Each list load gets a number; a response that is no longer the latest one is dropped.
    productsRequest: 0,
    categoriesRequest: 0,
    allCategoriesRequest: 0,
    usersRequest: 0,

    login: { email: '', password: '', message: null, busy: false },

    products: [],
    productsMeta: null,
    // Prices are typed in złoty, like in the product form.
    filters: { search: '', category_id: '', min_price: '', max_price: '', in_stock: false },
    filterErrors: {},
    categories: [],
    categoriesMeta: null,
    users: [],
    usersMeta: null,
    usersLoading: false,
    userFilters: { search: '', role: '' },
    userFilterErrors: {},
    // Every category across all pages, for names, select options and the parent column.
    allCategories: [],

    modal: { open: false, kind: null, id: null, form: {}, errors: {}, busy: false },

    async init() {
        if (this.token) {
            await this.boot();
        }
    },

    get isAdmin() {
        return this.user?.is_admin === true;
    },

    get topLevelCategories() {
        return this.allCategories.filter((c) => c.parent_id === null && c.id !== this.modal.id);
    },

    categoryName(id) {
        return this.allCategories.find((c) => c.id === id)?.name ?? '—';
    },

    formatPrice(grosze) {
        return `${groszeToZloty(grosze)} zł`;
    },

    async api(method, path, body) {
        const headers = { Accept: 'application/json' };
        if (body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }
        if (this.token) {
            headers.Authorization = `Bearer ${this.token}`;
        }

        const response = await fetch(`/api${path}`, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
        });

        const data = response.status === 204 ? null : await response.json().catch(() => null);

        if (response.status === 401 && this.token) {
            this.forgetSession('Sesja wygasła, zaloguj się ponownie.');
        }

        if (!response.ok) {
            throw new ApiError(response.status, data);
        }

        return data;
    },

    showFlash(type, text) {
        this.flash = { type, text };
        clearTimeout(this._flashTimer);
        this._flashTimer = setTimeout(() => (this.flash = null), 5000);
    },

    showError(error) {
        if (error.status === 401) {
            return;
        }
        const prefix = { 403: 'Brak uprawnień', 404: 'Nie znaleziono', 409: 'Konflikt', 429: 'Za dużo prób' }[error.status];
        this.showFlash('error', prefix ? `${prefix}: ${error.message}` : error.message);
    },

    // --- Session -------------------------------------------------------------

    async submitLogin() {
        this.login.busy = true;
        this.login.message = null;

        try {
            const data = await this.api('POST', '/login', { email: this.login.email, password: this.login.password });
            this.token = data.token;
            writeToken(this.token);
            this.login.password = '';
            await this.boot();
        } catch (error) {
            this.login.message = error.message;
        } finally {
            this.login.busy = false;
        }
    },

    async boot() {
        try {
            this.user = (await this.api('GET', '/me')).data;
        } catch (error) {
            // 401 already cleared the session; for anything else there is nothing to show without the user.
            if (error.status !== 401) {
                this.forgetSession(`Nie udało się połączyć z API: ${error.message}`);
            }
            return;
        }

        const loads = [this.loadProducts(1), this.loadCategories(1), this.loadAllCategories()];
        if (this.isAdmin) {
            loads.push(this.loadUsers(1));
        }
        await Promise.all(loads);
    },

    async logout() {
        try {
            await this.api('POST', '/logout');
        } catch {
            // The token is dropped locally either way.
        }
        this.forgetSession('Wylogowano.');
    },

    forgetSession(message) {
        this.token = null;
        this.user = null;
        // The next person to log in may not be an admin: never leave them on the users tab
        // or keep other people's emails in the page.
        this.tab = 'products';
        this.users = [];
        this.usersMeta = null;
        this.userFilters = { search: '', role: '' };
        this.userFilterErrors = {};
        // Responses still in flight from the old session must not land in the page.
        this.productsRequest++;
        this.categoriesRequest++;
        this.allCategoriesRequest++;
        this.usersRequest++;
        writeToken(null);
        this.modal.open = false;
        this.showFlash('info', message);
    },

    // --- Lists ---------------------------------------------------------------

    /** Query string for the product list, or null when a filter is invalid before even asking the API. */
    productsQuery(page) {
        const params = new URLSearchParams({ page });
        const errors = {};
        const search = this.filters.search.trim();

        // The API needs at least 2 characters; a single one just means "still typing".
        if (search.length >= 2) {
            params.set('search', search);
        }
        if (this.filters.category_id !== '') {
            params.set('category_id', this.filters.category_id);
        }
        for (const key of ['min_price', 'max_price']) {
            if (String(this.filters[key]).trim() === '') {
                continue;
            }
            const grosze = zlotyToGrosze(this.filters[key]);
            if (grosze === null) {
                errors[key] = ['Podaj kwotę w złotych, np. 49,99.'];
            } else {
                params.set(key, grosze);
            }
        }
        if (this.filters.in_stock) {
            params.set('in_stock', '1');
        }

        this.filterErrors = errors;

        return Object.keys(errors).length > 0 ? null : params.toString();
    },

    applyFilters() {
        this.loadProducts(1);
    },

    clearFilters() {
        this.filters = { search: '', category_id: '', min_price: '', max_price: '', in_stock: false };
        this.loadProducts(1);
    },

    /** Rows from an earlier query must not sit under an invalid filter as if they matched it. */
    clearProducts() {
        this.products = [];
        this.productsMeta = null;
    },

    get hasFilters() {
        const f = this.filters;
        return f.search.trim().length >= 2 || f.category_id !== '' || f.min_price !== '' || f.max_price !== '' || f.in_stock;
    },

    filterError(field) {
        return this.filterErrors[field]?.[0] ?? null;
    },

    async loadProducts(page) {
        const request = ++this.productsRequest;
        const query = this.productsQuery(page);
        if (query === null) {
            // The bump above already dropped any request in flight, so its finally won't reset these.
            this.productsLoading = false;
            this.clearProducts();
            return;
        }

        this.productsLoading = true;
        try {
            const data = await this.api('GET', `/products?${query}`);
            if (request !== this.productsRequest) {
                return;
            }
            // Deleting the last row of a page leaves it empty; step back to the new last page.
            if (data.data.length === 0 && page > 1) {
                return this.loadProducts(data.meta.last_page);
            }
            this.products = data.data;
            this.productsMeta = data.meta;
        } catch (error) {
            if (request !== this.productsRequest) {
                return;
            }
            if (error.status === 422) {
                this.filterErrors = error.errors;
                this.clearProducts();
            } else {
                this.showError(error);
            }
        } finally {
            if (request === this.productsRequest) {
                this.productsLoading = false;
            }
        }
    },

    async loadCategories(page) {
        const request = ++this.categoriesRequest;
        this.categoriesLoading = true;
        try {
            const data = await this.api('GET', `/categories?page=${page}`);
            if (request !== this.categoriesRequest) {
                return;
            }
            // Deleting the last row of a page leaves it empty; step back to the new last page.
            if (data.data.length === 0 && page > 1) {
                return this.loadCategories(data.meta.last_page);
            }
            this.categories = data.data;
            this.categoriesMeta = data.meta;
        } catch (error) {
            if (request === this.categoriesRequest) {
                this.showError(error);
            }
        } finally {
            if (request === this.categoriesRequest) {
                this.categoriesLoading = false;
            }
        }
    },

    /**
     * Walks every page (one request per 15 categories). Fine for a small catalog;
     * a bigger one would need a dedicated lookup endpoint.
     */
    async loadAllCategories() {
        const request = ++this.allCategoriesRequest;
        const all = [];
        let page = 1;
        let lastPage = 1;

        try {
            do {
                const data = await this.api('GET', `/categories?page=${page}`);
                all.push(...data.data);
                lastPage = data.meta.last_page;
                page++;
            } while (page <= lastPage);
        } catch (error) {
            if (request === this.allCategoriesRequest) {
                this.showError(error);
            }
            return;
        }

        if (request === this.allCategoriesRequest) {
            this.allCategories = all;
        }
    },

    usersQuery(page) {
        const params = new URLSearchParams({ page });
        const search = this.userFilters.search.trim();

        // The API needs at least 2 characters; a single one just means "still typing".
        if (search.length >= 2) {
            params.set('search', search);
        }
        if (this.userFilters.role !== '') {
            params.set('role', this.userFilters.role);
        }

        return params.toString();
    },

    get hasUserFilters() {
        return this.userFilters.search.trim().length >= 2 || this.userFilters.role !== '';
    },

    clearUserFilters() {
        this.userFilters = { search: '', role: '' };
        this.loadUsers(1);
    },

    userFilterError(field) {
        return this.userFilterErrors[field]?.[0] ?? null;
    },

    async loadUsers(page) {
        const request = ++this.usersRequest;
        this.usersLoading = true;
        this.userFilterErrors = {};
        try {
            const data = await this.api('GET', `/users?${this.usersQuery(page)}`);
            if (request !== this.usersRequest) {
                return;
            }
            // Deleting the last row of a page leaves it empty; step back to the new last page.
            if (data.data.length === 0 && page > 1) {
                return this.loadUsers(data.meta.last_page);
            }
            this.users = data.data;
            this.usersMeta = data.meta;
        } catch (error) {
            if (request !== this.usersRequest) {
                return;
            }
            if (error.status === 422) {
                // Rows from an earlier query must not sit under an invalid filter as if they matched it.
                this.userFilterErrors = error.errors;
                this.users = [];
                this.usersMeta = null;
                return;
            }
            this.showError(error);
            // Someone else may have taken our admin role away; re-read it so the tab disappears.
            if (error.status === 403) {
                await this.refreshMe();
                if (!this.isAdmin) {
                    this.tab = 'products';
                    this.users = [];
                    this.usersMeta = null;
                }
            }
        } finally {
            if (request === this.usersRequest) {
                this.usersLoading = false;
            }
        }
    },

    isSelf(user) {
        return user.id === this.user?.id;
    },

    formatDate(iso) {
        return new Date(iso).toLocaleDateString('pl-PL');
    },

    // --- Forms ---------------------------------------------------------------

    openProductForm(product = null) {
        this.modal = {
            open: true,
            kind: 'product',
            id: product?.id ?? null,
            errors: {},
            busy: false,
            form: product
                ? {
                    name: product.name,
                    slug: product.slug,
                    sku: product.sku,
                    price: groszeToZloty(product.price),
                    stock: product.stock,
                    description: product.description ?? '',
                    is_active: product.is_active,
                    // Select values are strings, so ids are kept as strings in the form.
                    category_id: String(product.category?.id ?? ''),
                }
                : emptyProduct(),
        };
    },

    openCategoryForm(category = null) {
        this.modal = {
            open: true,
            kind: 'category',
            id: category?.id ?? null,
            errors: {},
            busy: false,
            form: category
                ? { name: category.name, slug: category.slug, parent_id: String(category.parent_id ?? '') }
                : emptyCategory(),
        };
    },

    closeModal() {
        // An open request would otherwise finish into whichever modal is open next.
        if (!this.modal.busy) {
            this.modal.open = false;
        }
    },

    openUserForm(user) {
        this.modal = {
            open: true,
            kind: 'user',
            id: user.id,
            errors: {},
            busy: false,
            form: { name: user.name, email: user.email, is_admin: user.is_admin },
        };
    },

    userPayload() {
        const form = this.modal.form;

        return { name: form.name, email: form.email, is_admin: form.is_admin };
    },

    productPayload() {
        const form = this.modal.form;
        const price = zlotyToGrosze(form.price);
        const stock = String(form.stock).trim();
        const errors = {};

        if (price === null) {
            errors.price = ['Podaj cenę w złotych, np. 49,99.'];
        }
        if (!/^\d+$/.test(stock)) {
            errors.stock = ['Podaj stan jako liczbę całkowitą, np. 10.'];
        }
        if (Object.keys(errors).length > 0) {
            this.modal.errors = errors;
            return null;
        }

        return {
            name: form.name,
            slug: form.slug,
            sku: form.sku,
            price,
            stock: Number(stock),
            description: form.description === '' ? null : form.description,
            is_active: form.is_active,
            category_id: form.category_id === '' ? null : Number(form.category_id),
        };
    },

    categoryPayload() {
        const form = this.modal.form;

        return {
            name: form.name,
            slug: form.slug,
            parent_id: form.parent_id === '' ? null : Number(form.parent_id),
        };
    },

    async saveModal() {
        const kind = this.modal.kind;
        const payload = { product: () => this.productPayload(), category: () => this.categoryPayload(), user: () => this.userPayload() }[kind]();
        if (payload === null) {
            return;
        }

        const resource = RESOURCES[kind];
        const editing = this.modal.id !== null;

        const modal = this.modal;
        modal.busy = true;
        modal.errors = {};
        try {
            await this.api(editing ? 'PATCH' : 'POST', editing ? `${resource}/${modal.id}` : resource, payload);
        } catch (error) {
            if (error.status === 422) {
                modal.errors = error.errors;
            } else {
                this.showError(error);
            }
            return;
        } finally {
            modal.busy = false;
        }

        modal.open = false;
        this.showFlash('success', editing ? 'Zapisano zmiany.' : 'Dodano.');
        await this.refreshAfterWrite(kind);
    },

    async remove(kind, item) {
        if (!confirm(`Usunąć „${item.name}”?`)) {
            return;
        }

        try {
            await this.api('DELETE', `${RESOURCES[kind]}/${item.id}`);
        } catch (error) {
            this.showError(error);
            return;
        }

        this.showFlash('success', 'Usunięto.');
        await this.refreshAfterWrite(kind);
    },

    /**
     * Reloads what a write to `kind` (product, category or user) could have changed.
     * Category changes show up in product rows too (nested category), so both are refreshed.
     * Loaders report their own errors, so a failed reload never looks like a failed save.
     */
    async refreshAfterWrite(kind) {
        if (kind === 'user') {
            await Promise.all([this.loadUsers(this.usersMeta?.current_page ?? 1), this.refreshMe()]);
            return;
        }

        const reloads = [this.loadProducts(this.productsMeta?.current_page ?? 1)];
        if (kind === 'category') {
            reloads.push(this.loadCategories(this.categoriesMeta?.current_page ?? 1), this.loadAllCategories());
        }
        await Promise.all(reloads);
    },

    /** An admin may have just renamed themselves, so the header follows. */
    async refreshMe() {
        try {
            this.user = (await this.api('GET', '/me')).data;
        } catch (error) {
            this.showError(error);
        }
    },

    fieldError(field) {
        return this.modal.errors[field]?.[0] ?? null;
    },
}));

Alpine.start();
