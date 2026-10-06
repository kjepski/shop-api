<!DOCTYPE html>
<html lang="pl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Panel sklepu</title>

        @fonts

        <style>[x-cloak] { display: none !important; }</style>

        @vite(['resources/css/app.css', 'resources/js/admin.js'])
    </head>
    <body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">
        <div x-data="adminPanel" x-cloak>
            {{-- Flash message --}}
            <div x-show="flash" x-transition class="fixed top-4 right-4 z-50 max-w-sm rounded-md px-4 py-3 text-sm shadow-lg"
                 :class="{
                     'bg-green-600 text-white': flash?.type === 'success',
                     'bg-red-600 text-white': flash?.type === 'error',
                     'bg-gray-800 text-white': flash?.type === 'info',
                 }">
                <span x-text="flash?.text"></span>
            </div>

            {{-- Login --}}
            <template x-if="!token">
                <div class="flex min-h-screen items-center justify-center px-4">
                    <form @submit.prevent="submitLogin" class="w-full max-w-sm space-y-4 rounded-lg bg-white p-6 shadow">
                        <h1 class="text-xl font-semibold">Panel sklepu</h1>
                        <p class="text-sm text-gray-500">Zaloguj się kontem z API.</p>
                        @if (app()->isLocal())
                            {{-- Seeder accounts exist only in development, so the hint must not leak elsewhere. --}}
                            <p class="text-xs text-gray-400">Lokalnie: admin@example.com lub test@example.com, hasło: password.</p>
                        @endif

                        <p x-show="login.message" x-text="login.message" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                        <label class="block text-sm">
                            <span class="font-medium">E-mail</span>
                            <input type="email" x-model="login.email" required autocomplete="username"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium">Hasło</span>
                            <input type="password" x-model="login.password" required autocomplete="current-password"
                                   class="mt-1 w-full rounded border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                        </label>

                        <button type="submit" :disabled="login.busy"
                                class="w-full rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span x-text="login.busy ? 'Logowanie…' : 'Zaloguj'"></span>
                        </button>
                    </form>
                </div>
            </template>

            {{-- Panel --}}
            <template x-if="token">
                <div>
                    <header class="bg-white shadow">
                        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="flex items-center gap-6">
                                <span class="font-semibold">Panel sklepu</span>
                                <nav class="flex gap-1 text-sm">
                                    <button @click="tab = 'products'" class="rounded px-3 py-1.5"
                                            :class="tab === 'products' ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-100'">Produkty</button>
                                    <button @click="tab = 'categories'" class="rounded px-3 py-1.5"
                                            :class="tab === 'categories' ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-100'">Kategorie</button>
                                </nav>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span x-text="user?.name"></span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="isAdmin ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-700'"
                                      x-text="isAdmin ? 'admin' : 'tylko odczyt'"></span>
                                <button @click="logout" class="rounded border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Wyloguj</button>
                            </div>
                        </div>
                    </header>

                    <main class="mx-auto max-w-6xl px-4 py-6">
                        {{-- Products --}}
                        <section x-show="tab === 'products'">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="text-lg font-semibold">Produkty</h2>
                                <button x-show="isAdmin" @click="openProductForm()"
                                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Dodaj produkt</button>
                            </div>

                            <div class="overflow-x-auto rounded-lg bg-white shadow">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-left text-gray-600">
                                        <tr>
                                            <th class="px-4 py-2">Nazwa</th>
                                            <th class="px-4 py-2">SKU</th>
                                            <th class="px-4 py-2">Kategoria</th>
                                            <th class="px-4 py-2 text-right">Cena</th>
                                            <th class="px-4 py-2 text-right">Stan</th>
                                            <th class="px-4 py-2">Status</th>
                                            <th x-show="isAdmin" class="px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="product in products" :key="product.id">
                                            <tr :class="product.is_active ? '' : 'text-gray-400'">
                                                <td class="px-4 py-2">
                                                    <div class="font-medium" x-text="product.name"></div>
                                                    <div class="text-xs text-gray-400" x-text="product.slug"></div>
                                                </td>
                                                <td class="px-4 py-2 font-mono text-xs" x-text="product.sku"></td>
                                                <td class="px-4 py-2" x-text="product.category?.name ?? '—'"></td>
                                                <td class="px-4 py-2 text-right whitespace-nowrap" x-text="formatPrice(product.price)"></td>
                                                <td class="px-4 py-2 text-right" x-text="product.stock"></td>
                                                <td class="px-4 py-2">
                                                    <span class="rounded-full px-2 py-0.5 text-xs"
                                                          :class="product.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
                                                          x-text="product.is_active ? 'aktywny' : 'nieaktywny'"></span>
                                                </td>
                                                <td x-show="isAdmin" class="px-4 py-2 text-right whitespace-nowrap">
                                                    <button @click="openProductForm(product)" class="text-indigo-600 hover:underline">Edytuj</button>
                                                    <button @click="remove('product', product)" class="ml-3 text-red-600 hover:underline">Usuń</button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="products.length === 0">
                                            <td colspan="7" class="px-4 py-6 text-center text-gray-500">Brak produktów.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="productsMeta" class="mt-4 flex items-center justify-between text-sm text-gray-600">
                                <span x-text="`Strona ${productsMeta?.current_page} z ${productsMeta?.last_page} · ${productsMeta?.total} produktów`"></span>
                                <div class="flex gap-2">
                                    <button @click="loadProducts(productsMeta.current_page - 1)" :disabled="productsLoading || productsMeta?.current_page <= 1"
                                            class="rounded border border-gray-300 bg-white px-3 py-1.5 disabled:opacity-40">Poprzednia</button>
                                    <button @click="loadProducts(productsMeta.current_page + 1)" :disabled="productsLoading || productsMeta?.current_page >= productsMeta?.last_page"
                                            class="rounded border border-gray-300 bg-white px-3 py-1.5 disabled:opacity-40">Następna</button>
                                </div>
                            </div>
                        </section>

                        {{-- Categories --}}
                        <section x-show="tab === 'categories'">
                            <div class="mb-4 flex items-center justify-between">
                                <h2 class="text-lg font-semibold">Kategorie</h2>
                                <button x-show="isAdmin" @click="openCategoryForm()"
                                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Dodaj kategorię</button>
                            </div>

                            <div class="overflow-x-auto rounded-lg bg-white shadow">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-gray-50 text-left text-gray-600">
                                        <tr>
                                            <th class="px-4 py-2">Nazwa</th>
                                            <th class="px-4 py-2">Slug</th>
                                            <th class="px-4 py-2">Kategoria nadrzędna</th>
                                            <th x-show="isAdmin" class="px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <template x-for="category in categories" :key="category.id">
                                            <tr>
                                                <td class="px-4 py-2 font-medium" x-text="category.name"></td>
                                                <td class="px-4 py-2 text-gray-500" x-text="category.slug"></td>
                                                <td class="px-4 py-2" x-text="category.parent_id === null ? '—' : categoryName(category.parent_id)"></td>
                                                <td x-show="isAdmin" class="px-4 py-2 text-right whitespace-nowrap">
                                                    <button @click="openCategoryForm(category)" class="text-indigo-600 hover:underline">Edytuj</button>
                                                    <button @click="remove('category', category)" class="ml-3 text-red-600 hover:underline">Usuń</button>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="categories.length === 0">
                                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">Brak kategorii.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="categoriesMeta" class="mt-4 flex items-center justify-between text-sm text-gray-600">
                                <span x-text="`Strona ${categoriesMeta?.current_page} z ${categoriesMeta?.last_page} · ${categoriesMeta?.total} kategorii`"></span>
                                <div class="flex gap-2">
                                    <button @click="loadCategories(categoriesMeta.current_page - 1)" :disabled="categoriesLoading || categoriesMeta?.current_page <= 1"
                                            class="rounded border border-gray-300 bg-white px-3 py-1.5 disabled:opacity-40">Poprzednia</button>
                                    <button @click="loadCategories(categoriesMeta.current_page + 1)" :disabled="categoriesLoading || categoriesMeta?.current_page >= categoriesMeta?.last_page"
                                            class="rounded border border-gray-300 bg-white px-3 py-1.5 disabled:opacity-40">Następna</button>
                                </div>
                            </div>
                        </section>
                    </main>

                    {{-- Create / edit modal --}}
                    <div x-show="modal.open" x-transition.opacity class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4"
                         @keydown.escape.window="closeModal()">
                        <form @submit.prevent="saveModal" @click.outside="closeModal()"
                              class="max-h-[90vh] w-full max-w-lg space-y-4 overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                            <h3 class="text-lg font-semibold"
                                x-text="(modal.id ? 'Edytuj ' : 'Dodaj ') + (modal.kind === 'product' ? 'produkt' : 'kategorię')"></h3>

                            {{-- Product fields --}}
                            <template x-if="modal.kind === 'product'">
                                <div class="space-y-3">
                                    @foreach ([
                                        'name' => 'Nazwa',
                                        'slug' => 'Slug (np. grabie-ogrodowe)',
                                        'sku' => 'SKU (np. RAKE-001)',
                                        'price' => 'Cena w zł (np. 49,99)',
                                        'stock' => 'Stan magazynowy',
                                    ] as $field => $label)
                                        <label class="block text-sm">
                                            <span class="font-medium">{{ $label }}</span>
                                            <input type="text" x-model="modal.form.{{ $field }}"
                                                   class="mt-1 w-full rounded border px-3 py-2 focus:outline-none"
                                                   :class="fieldError('{{ $field }}') ? 'border-red-500' : 'border-gray-300 focus:border-indigo-500'">
                                            <span x-show="fieldError('{{ $field }}')" x-text="fieldError('{{ $field }}')" class="mt-1 block text-xs text-red-600"></span>
                                        </label>
                                    @endforeach

                                    <label class="block text-sm">
                                        <span class="font-medium">Kategoria</span>
                                        <select x-model="modal.form.category_id"
                                                class="mt-1 w-full rounded border px-3 py-2"
                                                :class="fieldError('category_id') ? 'border-red-500' : 'border-gray-300'">
                                            <option value="">— wybierz —</option>
                                            <template x-for="category in allCategories" :key="category.id">
                                                <option :value="String(category.id)" x-text="category.name"
                                                        :selected="String(category.id) === String(modal.form.category_id)"></option>
                                            </template>
                                        </select>
                                        <span x-show="fieldError('category_id')" x-text="fieldError('category_id')" class="mt-1 block text-xs text-red-600"></span>
                                    </label>

                                    <label class="block text-sm">
                                        <span class="font-medium">Opis</span>
                                        <textarea x-model="modal.form.description" rows="3"
                                                  class="mt-1 w-full rounded border px-3 py-2"
                                                  :class="fieldError('description') ? 'border-red-500' : 'border-gray-300'"></textarea>
                                        <span x-show="fieldError('description')" x-text="fieldError('description')" class="mt-1 block text-xs text-red-600"></span>
                                    </label>

                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" x-model="modal.form.is_active" class="rounded border-gray-300">
                                        <span>Aktywny (widoczny dla zwykłych użytkowników)</span>
                                    </label>
                                </div>
                            </template>

                            {{-- Category fields --}}
                            <template x-if="modal.kind === 'category'">
                                <div class="space-y-3">
                                    @foreach (['name' => 'Nazwa', 'slug' => 'Slug (np. narzedzia-ogrodowe)'] as $field => $label)
                                        <label class="block text-sm">
                                            <span class="font-medium">{{ $label }}</span>
                                            <input type="text" x-model="modal.form.{{ $field }}"
                                                   class="mt-1 w-full rounded border px-3 py-2 focus:outline-none"
                                                   :class="fieldError('{{ $field }}') ? 'border-red-500' : 'border-gray-300 focus:border-indigo-500'">
                                            <span x-show="fieldError('{{ $field }}')" x-text="fieldError('{{ $field }}')" class="mt-1 block text-xs text-red-600"></span>
                                        </label>
                                    @endforeach

                                    <label class="block text-sm">
                                        <span class="font-medium">Kategoria nadrzędna</span>
                                        <select x-model="modal.form.parent_id"
                                                class="mt-1 w-full rounded border px-3 py-2"
                                                :class="fieldError('parent_id') ? 'border-red-500' : 'border-gray-300'">
                                            <option value="">— brak (kategoria główna) —</option>
                                            <template x-for="category in topLevelCategories" :key="category.id">
                                                <option :value="String(category.id)" x-text="category.name"
                                                        :selected="String(category.id) === String(modal.form.parent_id)"></option>
                                            </template>
                                        </select>
                                        <span x-show="fieldError('parent_id')" x-text="fieldError('parent_id')" class="mt-1 block text-xs text-red-600"></span>
                                    </label>
                                </div>
                            </template>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="closeModal()" :disabled="modal.busy"
                                        class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Anuluj</button>
                                <button type="submit" :disabled="modal.busy"
                                        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                    <span x-text="modal.busy ? 'Zapisywanie…' : 'Zapisz'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </body>
</html>
