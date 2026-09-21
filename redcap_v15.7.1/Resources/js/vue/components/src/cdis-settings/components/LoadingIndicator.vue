<template>
    <div
        class="position-fixed top-0 start-50 translate-middle-x"
        style="z-index: 1030"
    >
        <div class="mt-2">
            <b-toast :autohide="false" visible title="Loading">
                <i class="fas fa-spinner fa-spin fa-fw me-2"></i>
                <span>Please wait...</span>
            </b-toast>
        </div>
    </div>
</template>

<script setup></script>

<style scoped></style>
