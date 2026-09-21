<template>
    <div data-wrapper class="position-absolute top-50 start-50 translate-middle">
        <span class="title">No Conversation Selected</span>
        <i class="icon fas fa-inbox"></i>
        <span class="description">Select a conversation to read.</span>
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