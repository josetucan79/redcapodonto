<template>
    <template v-if="parcel">
        <article class="parcel-detail">
            <header class="border-bottom p-2">
                <div class="d-flex">
                    <div>
                        <span class="d-block text-bold"
                            ><span class="detail-label">From:</span
                            >{{ parcel.from }}</span
                        >
                        <span class="d-block"
                            ><span class="detail-label">To:</span
                            >{{ parcel.to }}</span
                        >
                    </div>
                    <div class="ml-auto text-right">
                        <div class="time">
                            <small :title="parcel.createdAt" class="text-muted"
                                >created {{ createdAtDisplay }}</small
                            >
                            <small
                                :title="createdAtDescription"
                                class="text-muted"
                                ><i class="far fa-clock fa-fw"></i
                            ></small>
                            <small :title="parcel.expiration" class="text-muted"
                                >expires {{ parcel.readableExpiration }}</small
                            >
                            <small
                                :title="expirationDescription"
                                class="text-muted"
                                ><i class="far fa-hourglass fa-fw"></i
                            ></small>
                        </div>
                    </div>
                </div>
                <span class="d-block"
                    ><span class="detail-label">Subject:</span
                    >{{ parcel.subject }}</span
                >
            </header>

            <main class="p-2">
                <span class="d-block" v-html="parcel.body"></span>
            </main>
        </article>
    </template>
    <template v-else>
        <span>Parcel ID {{ id }} was not found</span>
    </template>
</template>

<script setup>
import moment from 'moment'
import { useParcelsStore } from '@/parcel/store'
import { computed, watch, watchEffect } from 'vue'
import { useRouter } from 'vue-router'

const store = useParcelsStore()
const router = useRouter()

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
})

const parcel = computed(() => {
    const found = store.list.find((parcel) => parcel.id === props.id)
    return found
})
watchEffect(() => {
    if (parcel.value) store.active = parcel.value
})

const active = computed(() => {
    return store.active
})
const createdAtDescription = computed(() => {
    return 'created at'
})
const expirationDescription = computed(() => {
    return 'expiration: date when the message will be automatically deleted'
})
const createdAtDisplay = computed(() => {
    const createdAtDate = parcel.value?.createdAt
    if (!createdAtDate) return 'no date available'
    const localTime = moment(createdAtDate).format('LT')
    const localDate = moment(createdAtDate).format('L')
    return `${localDate}, ${localTime}`
})

watch(
    parcel,
    (parcel) => {
        if (!parcel || parcel.read === true) return
        store.markParcel(parcel.id, true)
    },
    {
        immediate: true,
    }
)

watch(
    active,
    (value) => {
        // please note: immediate is set to false so
        // the component will not navigate to the inbox
        // if the route is accessed directly
        if (value === null) {
            // got to inbox if nothing is active
            router.push('/inbox')
        }
    },
    {
        immediate: false,
    }
)
</script>

<style scoped>
.parcel-detail {
}
.detail-label {
    margin-right: 3px;
    font-weight: 700;
}
.time {
    display: grid;
    grid-template-columns: auto min-content;
    gap: 0.5em;
    text-align: right;
}
main {
    white-space: pre-line;
}
</style>
