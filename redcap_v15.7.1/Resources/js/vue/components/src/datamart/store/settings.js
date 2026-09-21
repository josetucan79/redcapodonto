import { reactive } from 'vue'

export default () => {
    return reactive({
        ehr_system_name: '',
        lang: {},
        mapping_helper_url: '',
        project_id: 0,
        // standalone_authentication_flow: '',
        // standalone_launch_enabled: true,
        standalone_launch_url: '',
        user: {
            id: '',
            username: '',
            user_email: '',
            user_firstname: '',
        },
        date_range_categories: [],
        fhirMetadata: {},
    })
}
