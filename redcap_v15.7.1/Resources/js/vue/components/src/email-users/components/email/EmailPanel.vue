<template>
    <div class="email-properties d-flex flex-column gap-2">
        <div class="mb-2">
            <label for="email-from" class="form-label" v-tt:email_users_108>From</label>
            <div class="d-flex gap-2">
                <input type="text" id="display-name" class="form-control form-control-sm"
                    :placeholder="translate('email_users_151')" v-model="fromName" />
                <select class="form-select form-select-sm flex-grow-1" v-model="from">
                    <template v-for="(email, index) in emails">
                        <option :value="email">{{ email }}</option>
                    </template>
                </select>
            </div>
        </div>

        <div class="mb-2">
            <div class="d-flex gap-2 align-items-center">
                <label for="email-to" class="form-label" v-tt:email_users_109>To</label>
                <span class="small text-muted" >[<span v-tt:email_users_137></span>]</span>
            </div>
            <QueryToolbar />
        </div>


        <div class="mb-2">
            <div class="d-flex gap-2">
                <label for="email-subject" class="form-label" v-tt:email_users_10>Subject</label>
                <DynamicVariables @click="handleSubjectVariableSelected" />
            </div>
            <SubjectInput ref="subject-input" v-model="subject"/>
        </div>

        <div>
            <div class="d-flex gap-2">
                <label for="email-body" class="form-label" v-tt:email_users_114></label>
                <DynamicVariables @click="handleVariableSelected" />
            </div>
            <BodyTextarea class="form-control" v-model="body" ref="body-textarea"/>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, toRefs, useTemplateRef } from 'vue';
import { translate } from '../../../directives/TranslateDirective';
import { useEmailStore, useAppStore } from '../../store'
import QueryToolbar from './QueryToolbar.vue'
import BodyTextarea from './BodyTextarea.vue';
import DynamicVariables from './DynamicVariables.vue'
import SubjectInput from './SubjectInput.vue';

const emailStore = useEmailStore()
const appStore = useAppStore()

const {from, subject, body, fromName } = toRefs(emailStore)
const { user } = toRefs(appStore)
const emails = computed(() => user.value?.emails ?? [])

const bodyTextarea = useTemplateRef('body-textarea')
function handleVariableSelected(variable) {
  // Call the insertion method on BodyTextarea
  if (bodyTextarea.value && typeof bodyTextarea.value.insertDynamicVariable === 'function') {
    bodyTextarea.value.insertDynamicVariable(variable)
  }
}

const subjectInput = useTemplateRef('subject-input')
function handleSubjectVariableSelected(variable) {
  // Call the insertion method on BodyTextarea
  if (subjectInput.value && typeof subjectInput.value.insertDynamicVariable === 'function') {
    subjectInput.value.insertDynamicVariable(variable)
  }
}

</script>

<style scoped>
label {
    margin: 0;
}
.email-properties {
    /* display: grid; */
    /* grid-template-columns: min-content 1fr; */
    /* grid-template-columns: 1fr; */
    /* gap: 0.25rem; */
    /* gap: 10px; */
}
#display-name {
    width: min-content;
}
#display-name::placeholder {
  /* color: red; */
  /* opacity: 1 */
}
:deep(*) {

    :user-invalid {
        outline: 1px red solid;
    }
}

</style>