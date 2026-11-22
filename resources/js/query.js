// Filters -> query string object. Multi values are comma-separated
// (region=Leeds,York), never region[0]=, and empty values are dropped so the
// URL stays clean. Used for both searching and saving, so they can't differ.
export function toQuery(filters) {
    const query = {};

    for (const [key, value] of Object.entries(filters)) {
        const text = Array.isArray(value) ? value.join(',') : value;

        if (text !== '' && text !== null && text !== undefined) {
            query[key] = text;
        }
    }

    return query;
}
