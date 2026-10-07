/** Mirrors App\Http\Resources\UserResource. */
export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    created_at: string | null;
}
