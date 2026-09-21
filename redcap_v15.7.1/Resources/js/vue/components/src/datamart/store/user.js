import { reactive } from 'vue'

export default () => {
    return reactive({
        id: '',
        can_create_revision: false,
        can_repeat_revision: false,
        can_use_datamart: false,
        can_use_mapping_helper: false,
        has_valid_access_token: false,
        super_user: false,
        user_email: '',
        user_firstname: '',
        user_lastname: '',
        username: '',
        get canCreateRevision() {
            return Boolean(this.can_create_revision)
        },
        get canRepeatRevision() {
            return Boolean(this.can_repeat_revision)
        },
        get canUseDatamart() {
            return Boolean(this.can_use_datamart)
        },
        get hasValidAccessToken() {
            return Boolean(this.has_valid_access_token)
        },
        get isSuperUser() {
            return Boolean(this.super_user)
        },
        canRunRevision(revision) {
            const { metadata } = revision
            if (!metadata) return false
            const {
                approved = false,
                date = '',
                executed = false,
                executed_at = '',
                id = '',
                request_id = null,
                request_status = null,
                total_fetchable_mrns = 0,
                total_non_fetched_mrns = 0,
                total_project_mrns = 0,
            } = metadata
            if (total_fetchable_mrns <= 0) return false
            if (!approved) return false
            if (!this.canRepeatRevision && executed) return false
            return true
        },
    })
}
