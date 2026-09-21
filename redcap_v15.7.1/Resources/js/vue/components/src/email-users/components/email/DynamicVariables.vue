<template>
    <DropdownMenu label="Dynamic Variables" buttonVariant="outline-primary" buttonClass="btn-xs">
        <template v-for="(variables, category) in categories" :key="category">
            <DropdownItem :label="category" isSubmenu>
                <template v-for="(variable, label) in variables">
                    <DropdownItem @click="handleClick(variable)">
                        {{ label }}
                    </DropdownItem>
                </template>
            </DropdownItem>
        </template>
    </DropdownMenu>
</template>

<script setup>
import DropdownMenu from '@/shared/NestedDropdown/DropdownMenu.vue'
import DropdownItem from '@/shared/NestedDropdown/DropdownItem.vue'
import { useAppStore } from '../../store'
import { toRefs } from 'vue'

const appStore = useAppStore()
const {variables:categories} = toRefs(appStore)

const emit = defineEmits(['click'])

function handleClick(variable) {
    emit('click', variable)
}
</script>

<style scoped></style>
