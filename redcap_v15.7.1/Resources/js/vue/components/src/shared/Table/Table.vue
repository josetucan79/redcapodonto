<template>
    <table v-bind="{...$attrs}" :class="{striped:striped, hover:hover}">
        <thead>
            <template v-for="field in mapedFields" :key="`thead-${field.key}`">
                <th :class="{sortable: field.sortable}" @click="field.sortable && sortBy(field)">
                    <span class="th-wrapper">
                        <slot :name="`head(${field.key})`"
                        :field="field" :data="this" :value="field.label"
                        >{{field.label}}</slot>
                        <span data-sort-indicator v-if="field.sortable">
                            <span class="sort-index">{{ sortIndex(field) }}</span>
                            <span class="sort-direction"><i :class="sortIcon(field)"></i></span>
                        </span>
                    </span>
                </th>
            </template>
        </thead>
        <tbody>
            <template v-for="(item, itemIndex) in sortedItems" :key="`trow-${item?.id ?? itemIndex}`">
                <slot name="row" :item="item" :index="itemIndex" :colspan="mapedFields.length"></slot>
                <tr>
                    <template v-for="field in mapedFields" :key="`tcell-${field.key + (item?.id ?? itemIndex)}`">
                        <td :class="{ [`tcell-${field.key}`]: true }"><slot :name="`cell(${field.key})`"
                            :data="this" :item="item" :field="field"
                            :value="item[field.key]"
                        >{{item[field.key]}}</slot></td>
                    </template>
                </tr>
            </template>
        </tbody>
        <tfoot>
            <slot name="footer" :data="this"></slot>
        </tfoot>

        <template v-if="showEmpty && sortedItems.length===0">
            <tr class="p-5 text-muted font-italic">
                <td :colspan="mapedFields.length">
                    <slot name="empty" :items="sortedItems" :data="this" :fields="mapedFields"><span v-html="emptyText"></span></slot>
                </td>
            </tr>
        </template>
    </table>
</template>

<script>
import { ref, computed } from 'vue'
import {SORTDIRECTION, SortRule, multiSort} from './Sorter'
    
class Field {
    key='' // age
    label='' // Person age
    sortable=false // true
    /**
     * optional sorting logic for the field
     * a sort function accepts 2 parameters (a,b)
     * and follows the sorting rules
     */
     sortFn=null 

    constructor(data) {
        if(typeof data === 'string') {
            this.key = data
            this.label = data
        }else {
            this.key = data?.key
            this.label = data?.label
            this.sortable = data?.sortable || false
            this.sortFn = data?.sortFn || null
        }
    }
}

const extractKeysFromList = (items) => {
    let keys = []
    for (const item of items) {
        keys = keys.concat(Object.keys(item))
    }
    // remove duplicates
    keys = keys.filter( (item, index) => keys.indexOf(item) == index )
    return keys
}

export default {
    emits: ['sort'],
    setup(props, context) {

        const sorts = ref([])

        const sortedItems = computed( () => {
            if(props.externalSort || sorts.value.length==0) return props.items
            const items = [...props.items]
            let sorted = multiSort(sorts.value, items)
            return sorted
        })
        /**
         * map fields to Field objects
         */
        const mapedFields = computed( () => {
            let fields = props.fields
            if(fields.length===0) {
                fields = extractKeysFromList([...props.items])
            }
            return fields.map( field => new Field(field) )
        })

        function sortIndex(field) {
            const index = sorts.value.findIndex(item => item.key === field.key)
            if(index<0) return ''
            return index+1
        }
        function sortIcon(field) {
            const index = sorts.value.findIndex(item => item.key === field.key)
            if(index<0) return `fas fa-sort`
            const sortRule = sorts.value[index]
            if(sortRule.direction===SORTDIRECTION.ASC) return `fas fa-sort-up`
            else if(sortRule.direction===SORTDIRECTION.DESC) return `fas fa-sort-down`
            else return `far fa-exclamation-triangle`
        }
        function sortBy(field) {
            const { key } = field
            const index = sorts.value.findIndex(item => item.key === key)
            if(index<0) {
                const sortItem = new SortRule(key, SORTDIRECTION.ASC, field.sortFn)
                sorts.value.push(sortItem)
            } else {
                const sortItem = sorts.value[index]
                const direction = sortItem.direction
                if(direction===SORTDIRECTION.ASC) sortItem.direction = SORTDIRECTION.DESC
                else if(direction===SORTDIRECTION.DESC) {
                    sorts.value.splice(index,1)
                }
            }
            context.emit('sort', sorts.value, multiSort)
        }
        return {
            sorts,sortedItems,mapedFields,
            sortBy,sortIndex,sortIcon
        }
    },
    props: {
        fields: { type: Array, default: [] },
        items: { type: Array, default: [] },
        striped: { type: Boolean, default: false },
        hover: { type: Boolean, default: true },
        externalSort: { type: Boolean, default: false }, // sort externally
        showEmpty: { type: Boolean, default: false },
        emptyText: { type: String, default: 'nothing to display' },
    },
}
</script>

<style scoped>
table {
    --border-color: #dee2e6;
    border: 1px solid var(--border-color);
    table-layout: fixed;
    word-break: break-all;
    width: 100%;
} 
table th,
table td {
    padding: 2px 10px;
    border: solid 1px var(--border-color);
    position: relative;
}
th.sortable {
    cursor: pointer;
}
th  .th-wrapper {
    display: flex;
}
th [data-sort-indicator] {
    margin-left: auto;
    display: flex;
    align-items: center;
}
th [data-sort-indicator] .sort-index {
    font-size: 9px;
}
th [data-sort-indicator] .sort-direction {
    /* fixed weight */
    text-align: center;
    width: 1.25em;
}

.striped tr:nth-child( even ) {

}
.striped tr:nth-child( odd ) {
    background-color: rgba(0,0,0,.05);
}
.hover tr:hover {
    background-color: rgba(0,0,0,.075);
}

</style>