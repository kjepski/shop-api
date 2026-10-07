import type { Resource } from '@admin/types/api';
import type { User } from '@admin/types/user';

import { request } from './client';

export async function fetchMe(): Promise<User> {
    return (await request<Resource<User>>('GET', '/me')).data;
}

/** Cookie login of the panel (Sanctum SPA); no token is ever returned to JS. */
export async function login(email: string, password: string): Promise<User> {
    return (await request<Resource<User>>('POST', '/session', { body: { email, password } })).data;
}

export async function logout(): Promise<void> {
    await request<null>('POST', '/logout');
}
