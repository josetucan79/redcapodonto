<template>
  <div class="d-flex flex-column px-2 gap-0 border rounded">
    <div class="draggable-group d-flex flex-column">
        <template v-for="(child, index) in group.children" :key="qb.getNodeId(child?.node)" >
          <div class="child d-flex flex-column gap-2 border-dashed border-2"
              :draggable="dragEnabled"  @dragstart.self="onDragStart(child.node, index, $event)"
              @dragover.prevent="onDragOver(child.node, index, $event)"
              @dragend="onDragEnd(child.node, index, $event)"
              @drop.stop="onDropPromote(child.node, index, $event)">
          <!-- Render the child node (rule or group) -->
          <div class="operator" v-if="child.operator && index>0">
              <component :is="componentsRegistry.operatorSelect" v-model="child.operator" class="w-auto"/>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="drag-handle"
              @mousedown.stop="onHandleMouseDown"
              @mouseup.stop="onHandleMouseUp"
              >
              <i class="fa-solid fa-grip-lines"></i>
            </span>
              <component :is="componentsRegistry.queryNode" v-model="child.node" @drop.stop="onDrop(child.node, index, $event)" class="flex-grow-1 my-2" />
          </div>
          </div>
        </template>
    </div>
    <!-- Buttons to add new rule or group at this group level -->
    <div class="d-flex gap-2 my-2">
      <button class="btn btn-xs btn-light" @click="onAddRule">
        <i class="fas fa-circle-plus fa-fw text-success"></i>
      </button>
      <button class="btn btn-xs btn-light" @click="onAddGroup">
        <i class="fas fa-folder-plus fa-fw text-success"></i>
      </button>
      <template v-if="qb.root !== group">
        <button @click="qb.moveUp(group)" class="btn btn-xs btn-light" :disabled="!qb.canMoveUp(group)">
            <i class="fas fa-chevron-up fa-fw text-primary"></i>
        </button>
        <button @click="qb.moveDown(group)" class="btn btn-xs btn-light" :disabled="!qb.canMoveDown(group)">
            <i class="fas fa-chevron-down fa-fw text-primary"></i>
        </button>
        <button @click="qb.promoteNode(group)" class="btn btn-xs btn-light" :disabled="!qb.canBePromoted(group)">
          <i class="fas fa-arrow-up-from-bracket fa-fw text-primary"></i>
        </button>
        <button class="btn btn-xs btn-light" @click="onDeleteGroup">
          <i class="fas fa-trash fa-fw text-danger"></i>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { inject, ref } from 'vue';

import { CONDITIONS, OPERATORS } from '../../models/query-builder/Constants';

const dragEnabled = ref(false)
const group = defineModel({type: Object, required: true})
const debug = false

// Inject the global QueryBuilder instance.
const qb = inject('queryBuilder');
const componentsRegistry = inject('componentsRegistry');

const onAddRule = () => {
  const newRule = {
    field: null,
    condition: CONDITIONS.EQUAL.value,
  }
  // If there are existing children, use 'AND' as the default operator.
  const operator = OPERATORS.AND;
  qb.addRule(newRule, operator, group.value);
};

const onAddGroup = () => {
  const operator = group.value.children.length > 0 ? OPERATORS.AND : null;
  qb.addGroup(operator, group.value);
};

const onDeleteGroup = () => {
  qb.removeNode(group.value);
};


const onDragStart = (node, index, event) => {
  // save a reference to the element we want to move
  qb.draggedNode = node
  if(debug) console.log('onDragStart', node, index, event)
}
const onDragOver = (node, index, event) => {
  if(debug) console.log('onDragOver', child, index, event)
}
const onDrop = (node, index, event) => {
  qb.moveNode(qb.draggedNode, node, index)
  qb.draggedNode = null
  if(debug) console.log('onDrop', node)
}
const onDropPromote = (node, index, event) => {
  const parent = qb.getNodeParent(node)
  if(debug) console.log('onDropPromote', parent)
  if(!parent) return
  qb.moveNode(qb.draggedNode, parent, index)
  qb.draggedNode = null
}
const onDragEnd = (node, index, event) => {
  if(debug) console.log('onDragEnd', node, index, event, event.target)
  // qb.draggedChild = null
  dragEnabled.value = false
}

const onHandleMouseDown = () => dragEnabled.value = true
const onHandleMouseUp = () => dragEnabled.value = false
</script>

<style scoped>

.drag-handle {
  /* Show a "move" cursor so user knows it’s a handle */
  cursor: move;
}
</style>
