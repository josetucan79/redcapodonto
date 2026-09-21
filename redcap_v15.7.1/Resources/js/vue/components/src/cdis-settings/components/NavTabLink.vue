<template>
    <a
        v-if="isExternalLink"
        v-bind="$attrs"
        :href="to"
        target="_blank"
        :class="{ disabled }"
    >
        <slot />
    </a>
    <router-link
        v-else
        v-bind="$props"
        custom
        v-slot="{ isExactActive, href, navigate }"
    >
        <a
            class="nav-link"
            v-bind="$attrs"
            :href="href"
            @click.prevent="onClicked(navigate)"
            :class="{
                [isExactActive ? activeClass : inactiveClass]: true,
                disabled,
            }"
        >
            <slot />
        </a>
    </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
    ...RouterLink.props,
    activeClass: { type: String, default: 'active' },
    inactiveClass: { type: String, default: '' },
    disabled: { type: Boolean, default: false }, // if this function returns false, then stop the navigation
})

const isExternalLink = computed(
    () => typeof props.to === 'string' && props.to.startsWith('http')
)

function onClicked(navigate) {
    if (props.disabled === true) return
    navigate()
}
</script>
