<template>
    <div>
        <p class="alert alert-warning">
            Please be aware that you may need to run this tool multiple times, depending on the extent of modifications required for your project.
        </p>
        <p>The following actions should be performed:</p>
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">description</th>
                        <th scope="col">criticality</th>
                        <th scope="col">action type</th>
                    </tr>
                </thead>
                <tbody>
                    <template
                        v-for="(command, index) in commands"
                        :key="`${index}-${command?.id}`"
                    >
                        <tr class="command" :data-id="command.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <span class="description">{{
                                    command.description
                                }}</span>
                            </td>
                            <td class="text-center">
                                <span
                                    class="badge"
                                    :class="`bg-${getCriticalityVariant(
                                        command.criticality
                                    )}`"
                                    >{{ command.criticality }}</span
                                >
                            </td>
                            <td class="text-center">
                                <i
                                    class="fas"
                                    :class="
                                        getActionTypeIcon(command.action_type)
                                    "
                                    fixed-width
                                />
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    commands: { type: Array, default: () => [] },
})

function getCriticalityVariant(level) {
    switch (level) {
        case 1:
            return 'info'
        case 2:
            return 'primary'
        case 3:
            return 'warning'
        case 4:
            return 'danger'
        default:
            return 'secondary'
    }
}

function getActionTypeIcon(action_type) {
    switch (action_type) {
        case 'automatic_action':
            return ['fa-wand-magic-sparkles']
        case 'manual_action':
            return ['fa-user']
        default:
            return ['fa-question-circle']
    }
}
</script>

<style scoped>
table th {
    white-space: nowrap;
}
</style>
