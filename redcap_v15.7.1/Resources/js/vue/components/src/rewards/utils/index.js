export const formatCurrency = (amount, options = {}) => {
    const { locale = 'en-US', currency = 'USD' } = options
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency,
    }).format(amount)
}

export const paginate = (items, page = 1, perpage = 25) => {
    // Calculate the start index
    const start = (page - 1) * perpage
    const end = start + perpage
    // Calculate the end index
    // Return the slice of items from start to end
    console.log(page, perpage, start, end)
    return items.slice(start, end)
}
