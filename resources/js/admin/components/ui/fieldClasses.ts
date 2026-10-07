/** Shared look of text inputs, textareas and selects. */
export function inputClass(invalid: boolean): string {
    return [
        'block w-full rounded border px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none',
        invalid ? 'border-red-500' : 'border-gray-300',
    ].join(' ');
}
