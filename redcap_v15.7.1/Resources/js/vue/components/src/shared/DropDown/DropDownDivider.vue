<template>
    <hr data-dropdown-divider>
</template>

<script>
/**
 * specify data-no-close on an element to prevent closing the dropdown
 */


export default {
    setup (props, context) {
        return {}
    }
}
</script>

<style scoped>
[data-dropdown-divider] {
height: 0;
margin: var(--bs-dropdown-divider-margin-y) 0;
overflow: hidden;
border-top: 1px solid var(--bs-dropdown-divider-bg);
opacity: 1;
}


</style>