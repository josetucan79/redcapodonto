<template>
    <template v-if="data.type === 'folder'">
        <FolderNode :data="data">
            <template
                v-for="(child, index) in data.children"
                :key="`${index}-${child?.name}`"
            >
                <FileExplorer :data="child" :parent="data" />
            </template>
        </FolderNode>
    </template>
    <template v-else-if="data.type === 'file'">
        <FileNode :data="data" />
    </template>
</template>

<script setup>
import FileExplorer from './FileExplorer.vue'
import FolderNode from './FolderNode.vue'
import FileNode from './FileNode.vue'

const props = defineProps({
    data: { type: Object },
    parent: { type: Object, default: null },
})
</script>

<style scoped></style>
