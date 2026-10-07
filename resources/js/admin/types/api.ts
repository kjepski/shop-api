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
