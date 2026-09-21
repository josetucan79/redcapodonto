<template>
    <ul class="pagination" :class="size">
        <li data-first><button class="" :disabled="isFirstPage" v-html="firstText" @click="onFirstClicked"></button></li>
        <li data-prev><button class="" :disabled="isFirstPage" v-html="prevText" @click="onPrevClicked"></button></li>
        <template v-for="(page, index) in pages" :key="page">
            <template v-if="showEllipsis(index)">
                <li><button disabled class="" v-html="ellipsisText"></button></li>
            </template>
            <template v-else>
                <li data-prev :class="{active:modelValue===page}"><button class="" @click="onPageClicked(page)">{{page}}</button></li>
            </template>
        </template>
        <li data-next><button class="" :disabled="isLastPage" v-html="nextText" @click="onNextClicked"></button></li>
        <li data-last><button class="" :disabled="isLastPage" v-html="lastText" @click="onLastClicked"></button></li>
    </ul>
</template>

<script setup>
import { ref, toRefs, computed } from 'vue'

const props = defineProps({
    modelValue: { type: Number, default: 1 },
    perPage: { type: Number, default: 5 },
    maxVisibleButtons: { type: Number, default: 5 },
    totalItems: { type: Number, default: 0 },
    hideEllipsis: { type: Boolean, default: false },
    hideGotoEndButtons: { type: Boolean, default: false },
    firstText: { type: String, default: '«' },
    prevText: { type: String, default: '‹' },
    nextText: { type: String, default: '›' },
    lastText: { type: String, default: '»' },
    ellipsisText: { type: String, default: '…' },
    size: {
        type: String,
        default: '',
        validator(size) {
            return Object.values(SIZE).includes(size)
        },
    },
})

const emit = defineEmits(['update:modelValue'])

function range(size, startAt = 0) {
    return [...Array(size).keys()].map((i) => i + startAt)
}

const {
    modelValue: currentPage,
    maxVisibleButtons,
    hideEllipsis,
} = toRefs(props)
// amount of buttons before and after the central/active item
const delta = Math.floor(maxVisibleButtons.value / 2)

const pages = computed(() => {
    let totalButtons = maxVisibleButtons.value
    // manage first values: from 1 to delta
    let start = currentPage.value <= delta ? 1 : currentPage.value - delta
    // manage last values: from last to last-delta
    if (currentPage.value >= totalPages.value - delta)
        start = totalPages.value - totalButtons + 1
    if (start < 1) start = 1
    if (totalPages.value < totalButtons) totalButtons = totalPages.value
    const chunk = range(totalButtons, start)
    return chunk
})

const isFirstPage = computed(() => currentPage.value <= 1)
const isLastPage = computed(() => currentPage.value >= totalPages.value)

const totalPages = computed(() => {
    const { perPage, totalItems } = props
    return Math.ceil(totalItems / perPage)
})

function showEllipsis(index) {
    const lastIndex = maxVisibleButtons.value - 1
    if (hideEllipsis.value === true) return false // never hide if hideEllipsis set to true
    if (index === 0 && currentPage.value - delta - 1 <= 0) return false // manage first elements
    if (index === lastIndex && currentPage.value + delta >= totalPages.value) return false // manage first elements

    if (index > 0 && index < lastIndex) return false // hide only first and last
    // if(currentPage.value<=delta) return true
    return true
}

function onPageClicked(page) {
    if (page < 1) page = 1
    if (page > totalPages.value) page = totalPages.value
    emit('update:modelValue', page)
}
function onFirstClicked() {
    emit('update:modelValue', 1)
}
function onLastClicked() {
    emit('update:modelValue', totalPages.value)
}
function onPrevClicked() {
    let newPage = currentPage.value - 1
    if (newPage < 1) newPage = 1
    emit('update:modelValue', newPage)
}
function onNextClicked() {
    let newPage = currentPage.value+1
    if (newPage > totalPages.value) newPage = totalPages.value
    emit('update:modelValue', newPage)
}
</script>

<script>
const SIZE = Object.freeze({
    SMALL: 'sm',
    NORMAL: '',
    LARGE: 'lg',
})
</script>
<style scoped>
ul {
    list-style-type: none;
    display: flex;
    margin: 0;
    --padding: .375rem .75rem;
    --border-width: 1px;
    --primary-color: rgb(0 123 255);
    --inverted-color: rgb(255 255 255);
    --border-color: rgba(0 0 0 / 0.15);
    --hover-bg-color: rgb(43 48 53 / 0.05);
    --disabled-color: rgb(200 200 200);
    --border-radius: 3px;
    font-size: 1rem;
    line-height: 1.5;
}
ul.sm {
    --padding: .25rem .5rem;
    font-size: .875rem;
}
ul.lg {
    --padding: .5rem 1rem;
    font-size: 1.25rem;
}

button {
    box-sizing: border-box;
    position: relative;
    min-width: 40px;
    background-color: var(--inverted-color);
    color: var(--primary-color);
    padding: var(--padding);
    border-style: solid;
    border-width: var(--border-width);
    border-color: var(--border-color);
    border-radius: 0;
}
li + li button{
    border-left: 0;
}
button:not(:disabled):hover::before {
    content: '';
    position: absolute;
    inset: 0;
    background-color: var(--hover-bg-color);
}
button:disabled {
    color: var(--disabled-color);
}
li.active button {
    background-color: var(--primary-color);
    color: var(--inverted-color);
    font-weight: bold;
}
li:first-of-type button {
    border-top-left-radius: var(--border-radius);
    border-bottom-left-radius: var(--border-radius);
}
li:last-of-type button {
    border-top-right-radius: var(--border-radius);
    border-bottom-right-radius: var(--border-radius);
}
</style>