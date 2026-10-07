import { describe, expect, it } from 'vitest';

import { ApiError } from '@admin/api/client';
import { errorMessage, validationErrors } from '@admin/utils/errors';

describe('errorMessage', () => {
    it.each([
        [new ApiError(0, 'x'), 'Brak połączenia z serwerem. Sprawdź sieć i spróbuj ponownie.'],
        [new ApiError(401, 'Unauthenticated.'), 'Sesja wygasła, zaloguj się ponownie.'],
        [new ApiError(419, 'CSRF token mismatch.'), 'Sesja strony wygasła. Odśwież stronę i spróbuj ponownie.'],
        [new ApiError(403, 'This action is unauthorized.'), 'Brak uprawnień do tej operacji.'],
        [new ApiError(404, 'Not Found'), 'Nie znaleziono.'],
        [new ApiError(429, 'Too Many Attempts.', {}, 30), 'Za dużo prób. Spróbuj ponownie za 30 s.'],
        [new ApiError(429, 'Too Many Attempts.', {}, 0), 'Za dużo prób. Spróbuj ponownie za 0 s.'],
        [new ApiError(429, 'Too Many Attempts.'), 'Za dużo prób. Spróbuj ponownie za chwilę.'],
        [new ApiError(500, 'Server Error'), 'Błąd serwera. Spróbuj ponownie później.'],
        [new ApiError(503, 'Service Unavailable'), 'Błąd serwera. Spróbuj ponownie później.'],
        [new ApiError(409, 'Category has products.'), 'Category has products.'],
        [new Error('boom'), 'Coś poszło nie tak. Spróbuj ponownie.'],
        ['not an error', 'Coś poszło nie tak. Spróbuj ponownie.'],
    ])('describes %o', (error, expected) => {
        expect(errorMessage(error)).toBe(expected);
    });
});

describe('validationErrors', () => {
    it('returns the field errors of a 422', () => {
        const errors = { email: ['Required.'] };

        expect(validationErrors(new ApiError(422, 'Required.', errors))).toEqual(errors);
    });

    it('returns null for any other error', () => {
        expect(validationErrors(new ApiError(429, 'x'))).toBeNull();
        expect(validationErrors(new Error('x'))).toBeNull();
    });
});
