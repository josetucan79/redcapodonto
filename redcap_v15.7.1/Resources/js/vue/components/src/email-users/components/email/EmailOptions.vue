<template>
<details>
    <summary>
        <span class="fw-bold" v-tt:email_users_155>More options</span>
    </summary>
    <div class="border rounded p-2">

        <div>
            <label for="email-cc" class="form-label" v-tt:email_users_153>Cc</label>
            <input
                type="text"
                class="form-control form-control-sm"
                v-model="cc"
                placeholder="jane@example.com; john@mysite.org"
            />
        </div>

        <div>
            <label for="email-bcc" class="form-label" v-tt:email_users_154>Bcc</label>
            <input
                type="text"
                class="form-control form-control-sm"
                v-model="bcc"
                placeholder="jane@example.com; john@mysite.org"
            />
        </div>
    </div>
</details>
</template>

<script setup>
import { toRefs } from 'vue';
import { useEmailStore } from '../../store'

const emailStore = useEmailStore()
const { cc, bcc } = toRefs(emailStore)
</script>

<style scoped>

</style>