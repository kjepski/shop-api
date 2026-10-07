export interface TableColumn {
    /** Field of the row shown by default, and the name of the `cell-<key>` slot; also the API sort value. */
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right';
}

export interface SelectOption {
    value: string;
    label: string;
}
