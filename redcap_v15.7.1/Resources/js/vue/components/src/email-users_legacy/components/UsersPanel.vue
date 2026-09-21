<template>
    <UsersButtonGroups />

    <div class="d-flex my-2 align-items-center">
        <SendEmailsButton class="ms-auto" />
    </div>

    <div class="d-flex gap-2 align-items-center">
        <div class="me-auto">
            <UsersPagination />
        </div>
        <UsersPerPageDropdown />
        <UsersSearchField />
    </div>
    <div class="d-flex gap-2 align-items-center">
        <SuspendedUsersToggler class="my-2" />
        <UsersMetadata class="ms-auto" />
    </div>
    <UsersTable />
    <div class="d-flex gap-2">
        <UsersPagination class="me-auto" />
        <SendEmailsButton />
    </div>
</template>

<script setup>
import UsersButtonGroups from './UsersButtonGroups.vue'
import UsersMetadata from './UsersMetadata.vue'
import UsersTable from './UsersTable.vue'
import UsersSearchField from './UsersSearchField.vue'
import UsersPagination from './UsersPagination.vue'
import UsersPerPageDropdown from './UsersPerPageDropDown.vue'
import SendEmailsButton from './SendEmailsButton.vue'
import SuspendedUsersToggler from './SuspendedUsersToggler.vue'
</script>

<style scoped></style>
