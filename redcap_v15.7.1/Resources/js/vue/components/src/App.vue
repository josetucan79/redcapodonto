<template>
    <div>
        <div ref="containerRef"></div>
        <div ref="containerRef1"></div>
        <ul>
            <li>
                <a href="#" @click.prevent="launch('parcel')">Test Parcel</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('queue-monitor')"
                    >Test Queue Monitor</a
                >
            </li>
            <li>
                <a href="#" @click.prevent="launch('datamart')">DataMart</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('datamart-design-checker')">Datamart Design Checker</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('datamart-full')">Datamart Full</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('store-test')">Store test</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('mapping-helper')">Mapping Helper</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('cdis-settings')"
                    >CDIS Settings</a
                >
            </li>
            <li>
                <a href="#" @click.prevent="launch('web-worker')"
                    >Test Web Worker</a
                >
            </li>
            <li>
                <a href="#" @click.prevent="launch('rewards')">Test Rewards</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('modal-routes')">Modal Routes</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('cdp-auto-adjudicate')">CDP Auto Adjudication</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('cdp-mapping')">CDP Mapping</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('break-the-glass')">Break the Glass</a>
            </li>
            <li>
                <a href="#" @click.prevent="launch('email-users')">Email Users</a>
            </li>
        </ul>
    </div>

</template>

<script setup>
import { ref } from 'vue'
import CustomDrawer from './shared/CustomDrawer/CustomDrawer.vue'

let unmount
const containerRef = ref()
const containerRef1 = ref()

const open = ref(false)

const initTarget = (container) => {
    const parent = container
    while (parent.firstChild) {
        parent.removeChild(parent.firstChild)
    }
    const target = document.createElement('div')
    container.appendChild(target)
    return target
}

function onClick() {
    console.info('something was clicked')
}

const launch = (key) => {
    if (typeof unmount === 'function') {
        unmount()
        unmount = null
    }
    const target = initTarget(containerRef.value)
    const target1 = initTarget(containerRef1.value)
    switch (key) {
        case 'parcel':
            runParcel(target)
            break
        case 'queue-monitor':
            runQueueMonitor(target)
            break
        case 'datamart':
            runDatamart(target)
            break
        case 'store-test':
            runStoreTest(target)
            setTimeout(() => {
                runStoreTest(target1)
            }, 3000)
            break
        case 'datamart-design-checker':
            runDatamartDesignChecker(target)
            break
        case 'web-worker':
            runWebWorker(target)
            break
        case 'mapping-helper':
            testLib('MappingHelper', target)
            break
        case 'datamart-full':
            runDatamartFull(target, target1)
            break
        case 'cdis-settings':
            testLib('CdisSettings', target)
            break
        case 'rewards':
            testLib('Rewards', target)
            break
        case 'modal-routes':
            testLib('ModalRoutes', target)
            break
        case 'cdp-auto-adjudicate':
            testLib('CdpAutoAdjudicate', target)
            break
        case 'cdp-mapping':
            testLib('CdpMapping', target)
            break
        case 'break-the-glass':
            testLib('BreakTheGlass', target)
            break
        case 'email-users':
            testLib('EmailUsers', target)
            break
        default:
            break
    }
}

async function runParcel(target) {
    const { Parcel, ParcelBadge } = await import('./lib.js')

    const parcelApp = await Parcel(target)

    const badgeContainer = document.createElement('div')
    document.body.appendChild(badgeContainer)
    const badgeApp = await ParcelBadge(badgeContainer)

    unmount = () => {
        badgeApp.unmount()
        parcelApp.unmount()
    }
}

async function testLib(name, target) {
    const libs = await import('./lib.js')
    const lib = libs?.[name]
    if (!lib) return
    const { app } = lib(target)
    unmount = () => app.unmount()
}

async function testCdpAdjudicationTable(target) {
    let params = new URL(document.location).searchParams
    let recordID = params.get('record')
    const { CdpAdjudicationTable } = await import('./lib.js')
    CdpAdjudicationTable(target, 3)
}

async function runWebWorker(target) {
    const WebWorker = await import('./base-app/index')
    const app = WebWorker?.default(target)
    unmount = () => app.unmount()
}

async function runQueueMonitor(target) {
    const { QueueMonitor } = await import('./lib.js')
    const app = QueueMonitor(target)
    unmount = () => app.unmount()
}

async function runEmailUsers(target) {
    const { EmailUsers } = await import('./lib.js')
    const app = EmailUsers(target)
    unmount = () => app.unmount()
}

async function runDatamart(target) {
    const { Datamart } = await import('./lib.js')
    const { app, store, router } = Datamart(target)
    unmount = () => app.unmount()
}
async function runDatamartDesignChecker(target) {
    const { DatamartDesignChecker } = await import('./lib.js')
    const { app, store } = DatamartDesignChecker(target)
    unmount = () => app.unmount()
}
async function runDatamartFull(target, target1) {
    const { Datamart, DatamartDesignChecker } = await import('./lib.js')
    const { app } = Datamart(target)
    const { app: app1 } = DatamartDesignChecker(target1)
    unmount = () => {
        app.unmount()
        app1.unmount()
    }
}
async function runStoreTest(target) {
    const { StoreTest } = await import('./lib.js')
    const { app, store } = StoreTest(target)
    unmount = () => app.unmount()
}
</script>

<style scoped></style>
