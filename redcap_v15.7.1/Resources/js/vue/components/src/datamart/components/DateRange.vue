<template>
    <div class="d-flex gap-2 align-items-center">
        <label for="start-date">from</label>
        <input ref="minRef" type="date" id="start-date" class="form-control"
            :value="min" @change="onMinChanged" :max="max"/>
        <label for="end-date">to</label>
        <input ref="maxRef" type="date" id="end-date" class="form-control"
            :value="max" @change="onMaxChanged" :min="min"/>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import moment from 'moment'

const DATE_FORMAT = 'YYYY-MM-DD'

const props = defineProps({
    min: { type: String, default: '' },
    max: { type: String, default: '' },
})

const emit = defineEmits(['update:min', 'update:max'])

const minRef = ref()
const maxRef = ref()

function dateIsValid(value) {
    if (value === '') return true
    const date = moment(value)
    if (!date.isValid()) return false
    return true
}

const dateRangeValid = computed(() => testDateRange(props.min, props.max))

function testDateRange(start, end) {
    if (!dateIsValid(start) || !dateIsValid(end)) return false
    if (start === '' || end === '') return true
    if (moment(start).isAfter(moment(end))) return false
    return true
}

function onMinChanged() {
    let value = minRef?.value?.value
    const date = moment(value)
    if (!date.isValid()) {
        emit('update:min', '')
        return
    }
    value = date.format(DATE_FORMAT)
    emit('update:min', value)
    const otherDate = moment(maxRef?.value?.value)
    if (otherDate.isValid() && otherDate.isBefore(date)) {
        emit('update:max', value)
    }
}

function onMaxChanged() {
    let value = maxRef?.value?.value
    const date = moment(value)
    if (!date.isValid()) {
        emit('update:max', '')
        return
    }
    value = date.format(DATE_FORMAT)
    emit('update:max', value)
    const otherDate = moment(minRef?.value?.value)
    if (otherDate.isValid() && otherDate.isAfter(date)) {
        emit('update:min', value)
    }
}
</script>

<style lang="scss" scoped></style>
