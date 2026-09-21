<template>
    <div class="wrapper">
        <aside>
            <router-view name="Aside"></router-view>
        </aside>
        <main>
            <div class="detail">
                <router-view></router-view>
            </div>
        </main>
    </div>
</template>

<script>
export default {

}
</script>

<style scoped>


.wrapper {
    display: grid;
    grid-template-columns: 3fr 5fr;
    border: solid 1px var(--border-color);
    border-radius: .25rem;
    /* min-height: 100%; */
}
.detail {
    height: 100%;
    position: relative;
}
aside {
    /* max-width: 500px; */
    margin: 0;
    padding: 0;
    position: relative;
    border-right: solid 1px var(--border-color);
}

</style>