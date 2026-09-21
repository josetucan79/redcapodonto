<template>
    <ul class="nav nav-tabs">
        <NavLink :to="{ name: 'home' }">Home</NavLink>
        <NavLink :to="{ name: 'custom-request' }">Custom request</NavLink>
    </ul>
</template>

<script setup>
import { h } from 'vue'
import { RouterLink, useLink } from 'vue-router'

const NavLink = {
    props: { ...RouterLink.props },
    setup(props, { slots }) {
        const { href, isExactActive } = useLink(props)
        return () =>
            h(RouterLink, { ...props, custom: true }, () =>
                h(
                    NavItem,
                    { active: isExactActive.value, href: href.value },
                    () => slots.default()
                )
            )
    },
}

const NavItem = {
    props: {
        active: { type: Boolean },
        href: { type: String },
    },
    setup(props, { slots }) {
        return () =>
            h(
                'li',
                {
                    class: { 'nav-item': true },
                },
                [
                    h(
                        'a',
                        {
                            class: { active: props.active, 'nav-link': true },
                            href: props.href,
                        },
                        slots.default()
                    ),
                ]
            )
    },
}
</script>

<style scoped>
a {
    text-decoration: none;
}
</style>
