<template>
    <div class="rounded border">
      {{ qb }}
        <router-view></router-view>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { QueryBuilder } from '../models/query-builder';

const json = {
  "type": "group",
  "children": [
    {
      "operator": "AND",
      "node": {
        "type": "rule",
        "field": "user_email",
        "condition": "is not equal",
        "value": "past_3_months"
      }
    },
    {
      "operator": "AND_NOT",
      "node": {
        "type": "rule",
        "field": "user_expiration_date_TANGE",
        "condition": "is between",
        "value": null
      }
    }
  ]
}


const qb = ref(QueryBuilder.fromJSON(json))
</script>

<style scoped>
</style>
