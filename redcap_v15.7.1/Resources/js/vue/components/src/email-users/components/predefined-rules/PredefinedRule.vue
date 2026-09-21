<template>
    <div class="rule d-flex gap-2 align-items-end border-start border-2 ps-2" :key="qb.getNodeId(rule)">
        <!-- FIELD DROPDOWN -->
        <div>
            <label class="form-label" for="field">Field:</label>
            <select
                class="form-select form-select-sm"
                id="field"
                v-model="rule.field"
            >
                <option value="" disabled>Select...</option>
                <option
                    v-for="fieldConfig in config"
                    :key="fieldConfig.name"
                    :value="fieldConfig.name"
                >
                    {{ fieldConfig.label }}
                </option>
            </select>
        </div>
        <!-- CONDITION DROPDOWN -->
        <div>
            <label class="form-label" for="condition">Condition:</label>
            <select
                class="form-select form-select-sm"
                id="condition"
                v-model="rule.condition"
                :disabled="!rule.field"
            >
                <option value="" disabled>Select...</option>
                <option
                    v-for="(conditionConfig, condition) in currentConditions"
                    :key="condition"
                    :value="condition"
                >
                    {{ condition }}
                </option>
            </select>
        </div>

        <!-- VALUE INPUT (DYNAMIC) -->
        <div>
            <label class="form-label">Value:</label>
            <!-- 
            We'll pick a sub-component based on the selected condition.
            For "between", we might show 2 inputs; for everything else, 1 input, etc.
            -->
            <template v-if="!currentConditionConfig">
                <input class="form-control form-control-sm" type="text" disabled />
            </template>
            <template v-else-if="currentConditionConfig?.inputType === 'select'">
                <SelectValueInput v-model="rule.values" :config="currentConditionConfig" />
            </template>
            <template v-else-if="currentConditionConfig?.inputType === 'date'">
                <DateValueInput v-model="rule.values" :config="currentConditionConfig" />
            </template>
            <template v-else-if="currentConditionConfig?.inputType === 'date_range'">
                <DateRangeValueInput v-model="rule.values" :config="currentConditionConfig" />
            </template>
            <template v-else-if="currentConditionConfig?.inputType === 'null'">
                <NullValueInput v-model="rule.values" :config="currentConditionConfig" />
            </template>
            <template v-else>
                <StringValueInput v-model="rule.values" :config="currentConditionConfig"/>
            </template>
        </div>

        <!-- REMOVE BUTTON -->
        <div class="ms-auto actions d-flex gap-2">
            <button @click="qb.moveUp(rule)" class="btn btn-xs btn-light" :disabled="!qb.canMoveUp(rule)">
                <i class="fas fa-chevron-up fa-fw text-primary"></i>
            </button>
            <button @click="qb.moveDown(rule)" class="btn btn-xs btn-light" :disabled="!qb.canMoveDown(rule)">
                <i class="fas fa-chevron-down fa-fw text-primary"></i>
            </button>
            <button @click="qb.promoteNode(rule)" class="btn btn-xs btn-light" :disabled="!qb.canBePromoted(rule)">
                <i class="fas fa-arrow-up-from-bracket fa-fw text-primary"></i>
            </button>
            <button
                class="btn btn-xs btn-light"
                type="button"
                @click="onRemoveRule"
            >
                <i class="fas fa-trash fa-fw text-danger"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, watch } from 'vue'
import SelectValueInput from './inputs/SelectValueInput.vue'
import DateValueInput from './inputs/DateValueInput.vue'
import DateRangeValueInput from './inputs/DateRangeValueInput.vue'
import StringValueInput from './inputs/StringValueInput.vue'
import NullValueInput from './inputs/NullValueInput.vue'

const rule = defineModel({
    type: Object,
    required: true,
})

// plus a `config` array describing allowed fields and conditions.
const props = defineProps({
    config: {
        type: Array,
        required: true,
    },
})

// The query builder instance, so we can remove the rule if desired.
const qb = inject('queryBuilder')

// Current field definition from the config array
const fieldConfiguration = computed(() => {
    return props.config.find((f) => f.name === rule.value.field)
})

// The conditions available for the currently selected field
const currentConditions = computed(() => {
    if (!fieldConfiguration.value) return []
    return fieldConfiguration.value.conditions || []
})

const currentConditionConfig = computed(() => {
    const condition = rule.value?.condition
    const conditionConfig = currentConditions.value?.[condition] ?? null
    return conditionConfig
})

// If the user changes the field, we may need to reset the condition if it's not valid
watch(
    rule,
    () => {
        const conditions = currentConditions.value
        if (!conditions || !Object.keys(conditions).includes(rule.value.condition)) {
            rule.value.condition = ''
        }
    },
    { deep: true }
)

// reset rule properties if the field is changed
watch(
    () => rule.value.field,
    (currentRule, beforeRule) => {
        // console.log('rule has changed', currentRule, beforeRule)
        rule.value.values = []
        rule.value.condition = null
    }
)
// reset the value if the condition changes
watch(
    currentConditionConfig,
    (current, previous) => {
        // reset value if the input type changes
        if(current?.inputType !== previous?.inputType) {
            rule.value.values = []
        }
    }
)

function onRemoveRule() {
    qb.removeNode(rule.value)
}
</script>

<style scoped>
label {
    display: none;
}
.rule .actions {
    transition-property: opacity;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
    opacity: 0;
}
.rule:hover .actions {
    opacity: 1;
}
</style>
