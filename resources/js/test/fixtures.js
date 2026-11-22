// Prop shapes copied from the Laravel resources/controllers.
export const options = {
    propertyTypes: [{ value: 'terraced', label: 'Terraced' }, { value: 'flat', label: 'Flat' }],
    regionOptions: ['Leeds', 'Liverpool'],
    priceOptions: [{ value: 100000, label: '£100,000' }, { value: 200000, label: '£200,000' }, { value: 300000, label: '£300,000' }],
    bedroomOptions: [{ value: 1, label: '1' }, { value: 2, label: '2' }],
    bathroomOptions: [{ value: 1, label: '1' }],
};

export const noFilters = {
    min_price: null, max_price: null, min_bedrooms: null, max_bedrooms: null,
    min_bathrooms: null, max_bathrooms: null, region: [], property_type: [],
};

export const listing = (id = 1, extra = {}) => ({
    id, price: 250000, address_line_1: `${id} Park Lane`, city: 'Leeds', postcode: 'LS1 1AA',
    bedrooms: 3, bathrooms: 2, property_type_label: 'Terraced', image_url: '/img/1.webp',
    branch: { name: 'Leeds Central', region: 'Yorkshire' }, ...extra,
});

export const paged = (data) => ({
    data,
    meta: { current_page: 1, last_page: 1, total: data.length },
    links: { prev: null, next: null },
});
