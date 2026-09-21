# Autocomplete

A customizable library for displaying data using autocomplete.

### Example usage
```html
<input type="search" id="searchInput" />
<div id="results"></div>
```
```javascript
// Example callback function to process the given payload
function processRecipesResponse(response) {
    return response.recipes.map(recipe => ({
        label: `<img src="${recipe.image}" style="height: 50px; "/><b>${recipe.name}</b>`,
        value: recipe.name,
    }));
}

new AutoComplete('#searchInput', {
    results: '#results',
    remoteURL: 'https://dummyjson.com/recipes/search',
    processResponse: processRecipesResponse,
});
```