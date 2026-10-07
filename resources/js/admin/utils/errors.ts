import { ApiError } from '@admin/api/client';
import type { ValidationErrors } from '@admin/types/api';

/** A message for the user, in Polish, for any error thrown while talking to the API. */
export function errorMessage(error: unknown): string {
    if (!(error instanceof ApiError)) {
        return 'Coś poszło nie tak. Spróbuj ponownie.';
    }

    if (error.status === 0) {
        return 'Brak połączenia z serwerem. Sprawdź sieć i spróbuj ponownie.';
    }
    if (error.status === 401) {
        return 'Sesja wygasła, zaloguj się ponownie.';
    }
    if (error.status === 419) {
        // Left after the client's single retry: cookies blocked or a misconfigured session domain.
        return 'Sesja strony wygasła. Odśwież stronę i spróbuj ponownie.';
    }
    if (error.status === 403) {
        return 'Brak uprawnień do tej operacji.';
    }
    if (error.status === 404) {
        return 'Nie znaleziono.';
    }
    if (error.status === 429) {
        return error.retryAfter !== null
            ? `Za dużo prób. Spróbuj ponownie za ${error.retryAfter} s.`
            : 'Za dużo prób. Spróbuj ponownie za chwilę.';
    }
    if (error.status >= 500) {
        return 'Błąd serwera. Spróbuj ponownie później.';
    }

    return error.message;
}

/** Field errors of a 422 response, or null for any other error. */
export function validationErrors(error: unknown): ValidationErrors | null {
    return error instanceof ApiError && error.status === 422 ? error.errors : null;
}
