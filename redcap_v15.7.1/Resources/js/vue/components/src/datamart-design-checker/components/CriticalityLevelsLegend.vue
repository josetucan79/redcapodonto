<template>
    <div class="card" style="width: 18rem">
        <div class="card-body">
            <h5 class="card-title">Criticality levels</h5>
            <div>
                <section>
                    <span>
                        <span
                            class="badge"
                            :class="`bg-${getCriticalityVariant(1)}`"
                            >1</span
                        >: low</span
                    >
                </section>
                <section>
                    <span>
                        <span
                            class="badge"
                            :class="`bg-${getCriticalityVariant(2)}`"
                            >2</span
                        >: medium</span
                    >
                </section>
                <section>
                    <span>
                        <span
                            class="badge"
                            :class="`bg-${getCriticalityVariant(3)}`"
                            >3</span
                        >: high</span
                    >
                </section>
                <section>
                    <span>
                        <span
                            class="badge"
                            :class="`bg-${getCriticalityVariant(4)}`"
                            >4</span
                        >: critical</span
                    >
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
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
</script>

<style scoped></style>
