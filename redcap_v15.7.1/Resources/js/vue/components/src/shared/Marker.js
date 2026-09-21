import { h } from 'vue'

const Marker = {
    props: {
        query: { type: String, default: '' },
    },
    setup(props, context) {
        
    },
    template: `
        <span><slot>hello</slot></span>
    `
}

export default Marker
