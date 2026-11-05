export const formatDate = (value) =>
    value ? new Date(value).toLocaleDateString() : '';

export const formatDateTime = (value) =>
    value ? new Date(value).toLocaleString() : '';
