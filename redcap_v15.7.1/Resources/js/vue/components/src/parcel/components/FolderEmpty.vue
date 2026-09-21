<template>
    <div data-wrapper>
        <span class="title">Folder is empty</span>
        <i class="icon fa-regular fa-folder-open"></i>
        <span class="description">Nothing to select.</span>
    </div>
</template>

<script setup></script>

<style scoped>
[data-wrapper] {
    display: grid;
    display: grid;
    grid-template-columns: auto;
    grid-template-rows: auto;
}
div > * {
    display: block;
    align-items: center;
    justify-content: center;
    align-self: center;
    margin: auto;
}
.icon {
    font-size: 2.5em;
}
.title {
    font-size: 1.3em;
    font-weight: bold;
}
.description {
    font-size: .9em;
}
</style>