<template>
    <div class="border rounded p-2">
        <h5><tt-text tkey="email_users_103" /></h5>
        <div class="button-grid">
            <div class="state">
                <div class="button-group">
                    <UsersButtonGroup :group="GROUPS.ACTIVE">
                        <tt-text tkey="email_users_19" /><sup> *</sup>
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.NON_ACTIVE">
                        <tt-text tkey="email_users_20" /><sup> *</sup>
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.LOGGED_IN">
                        <tt-text tkey="email_users_34" />
                    </UsersButtonGroup>
                </div>
            </div>
            
            <div class="feature">
                <span class="font-weight-bold"><tt-text tkey="email_users_104" />:</span>
                <div class="button-group">
                    <UsersButtonGroup :group="GROUPS.API_TOKEN">
                        <tt-text tkey="email_users_116" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.MOBILE_APP_RIGHTS">
                        <tt-text tkey="email_users_117" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.PROJECT_OWNERS">
                        <tt-text tkey="email_users_118" /> <sup>**</sup>
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.CDIS">
                        <tt-text tkey="email_users_119" />
                    </UsersButtonGroup>
                </div>
            </div>
                
            <div class="auth-type">
                <span class="font-weight-bold"><tt-text tkey="email_users_105" />:</span>
                <div class="button-group">
                    <UsersButtonGroup :group="GROUPS.TABLE_BASED">
                        <tt-text tkey="email_users_31" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.LDAP">
                        <tt-text tkey="email_users_32" />
                    </UsersButtonGroup>
                </div>
            </div>

            <div class="activity">
                <span class="font-weight-bold"><tt-text tkey="email_users_106" />:</span>
                <div class="button-group">
                    <UsersButtonGroup :group="GROUPS.INTERVAL_PAST_WEEK">
                        <tt-text tkey="email_users_107" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.INTERVAL_PAST_MONTH">
                        <tt-text tkey="email_users_24" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.INTERVAL_PAST_3_MONTHS">
                        <tt-text tkey="email_users_27" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.INTERVAL_PAST_6_MONTHS">
                        <tt-text tkey="email_users_25" />
                    </UsersButtonGroup>
                    <UsersButtonGroup :group="GROUPS.INTERVAL_PAST_12_MONTHS">
                        <tt-text tkey="email_users_26" />
                    </UsersButtonGroup>
                </div>
            </div>
        </div>

        <ul class="mt-2 notes-descriptions font-italic small text-muted text-end">
            <li><span>*</span> <tt-text tkey="email_users_30"/></li>
            <li><span>**</span> <tt-text tkey="email_users_102"/></li>
        </ul>
    </div>
</template>

<script setup>
import { GROUPS } from '../models/UsersManager'
import UsersButtonGroup from './UsersButtonGroup.vue'
</script>

<style scoped>
.button-grid {
  display: grid;
  grid-gap: 10px;
}

.button-group {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  grid-gap: 10px;
}
.button-group button {
    position: relative;
}
.notes-descriptions {
  list-style-type: none;
  margin: 0;
  padding: 0;
}
.note-reference {

}
</style>