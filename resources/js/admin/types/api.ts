/** Field name -> messages, as in Laravel's 422 response. */
export type ValidationErrors = Record<string, string[]>;

/** Body of a Laravel JSON error response. */
export interface ErrorBody {
    message?: string;
    errors?: ValidationErrors;
}

/** A single model wrapped by a Laravel API Resource. */
export interface Resource<T> {
    data: T;
}

/** `meta` of a Laravel paginated resource collection (the fields the panel uses). */
export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    /** Position of the first row on this page; null when the page is empty. */
    from: number | null;
    to: number | null;
}

/** A page of a Laravel resource collection (`->paginate()`). */
export interface Paginated<T> {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: PaginationMeta;
}
