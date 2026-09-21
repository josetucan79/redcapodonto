
// import {FieldConfig} from './models/field-config/FieldConfig'

export class FieldConfig {
  name
  label
  conditions

  constructor({name='', label='', conditions=[], options=null}) {
    this.name = name
    this.label = label
    this.conditions = conditions
    this.options = options
  }

  toJSON() {
    return {
      name: this.name,
      label: this.label,
      conditions: this.conditions,
    }
  }
}

const CONDITIONS = Object.freeze({
  EQUAL: 'is equal',
  NOT_EQUAL: 'is not equal',
  LESS_THAN: 'is less than',
  LESS_THAN_EQUAL: 'is less than or equal',
  GREATER_THAN: 'is greater than',
  GREATER_THAN_EQUAL: 'is greater than or equal',
  IS_BETWEEN: 'is between',
  IS_NOT_BETWEEN: 'is not between',
  IS_IN: 'is in',
  IS_NOT_IN: 'is not in',
  CONTAINS: 'contains',
  DOES_NOT_CONTAIN: 'does not contain',
  BEGINS_WITH: 'begins with',
  DOES_NOT_BEGIN_WITH: 'does not begin with',
  ENDS_WITH: 'ends with',
  DOES_NOT_ND_WITH: 'does not end with',
  IS_NULL: 'is null',
  IS_NOT_NULL: 'is not null',
  IS:'is',
  IS_NOT:'is not',
  HAS:'has',
  HAS_NOT:'has not',
  IS_WITHIN:'is within',
  IS_NOT_WITHIN:'is not within',
})

class ConditionConfig {
  constructor(params = {}) {
    this.inputType = params?.inputType ?? 'string';
    this.requiresValue = params?.requiresValue ?? true;
    this.options = params?.options ?? [];
  }

  toJSON() {
    return {
      inputType: this.inputType,
      requiresValue: this.requiresValue,
      options: this.options,
    }
  }
}

const user_status_options = () =>{
  return {
    'active': 'active',
    'logged_in': 'logged in',
    'CDIS_user': 'CDIS user',
    'project_owner': 'project owner',
  }
}
const user_privileges_options = () =>{
  return {
    'mobile_app_rights': 'mobile app rights',
    'API_token': 'API token',
  }
}
const user_activity_options = () =>{
  return {
    'past_week': 'past week',
    'past_month': 'past month',
    'past_3_months': 'past 3 months',
    'past_6_months': 'past 6 months',
    'past_12_months': 'past 12 months',
  }
}
const user_authentication_type_options = () =>{
  return {
    'table_based': 'table based',
    'LDAP': 'LDAP',
  }
}
const project_purpose_options = () =>{
  return {
    'practice': 'Practice',
    'operational_support': 'Operational Support',
    'research': 'Research',
    'quality_improvement': 'Quality Improvement',
    'other': 'Other',
  }
}

// project purpose
// authentication type

// fieldsConfig.js
export default [
    new FieldConfig({
      name: 'user_status',
      label: 'User Status',
      conditions: {
        [CONDITIONS.IS]: new ConditionConfig({inputType:'select', options: user_status_options()}),
        [CONDITIONS.IS_NOT]: new ConditionConfig({inputType:'select', options: user_status_options()}),
      },
    }),
    new FieldConfig({
      name: 'user_privileges',
      label: 'User Privileges',
      conditions: {
        [CONDITIONS.HAS]: new ConditionConfig({inputType:'select', options: user_privileges_options()}),
        [CONDITIONS.HAS_NOT]: new ConditionConfig({inputType:'select', options: user_privileges_options()}),
      },
    }),
    new FieldConfig({
      name: 'user_activity',
      label: 'User Activity',
      conditions: {
        [CONDITIONS.IS_WITHIN]: new ConditionConfig({inputType:'select', options: user_activity_options()}),
        [CONDITIONS.IS_NOT_WITHIN]: new ConditionConfig({inputType:'select', options: user_activity_options()}),
      },
    }),
    new FieldConfig({
      name: 'user_authentication_type',
      label: 'User Authentication Type',
      conditions: {
        [CONDITIONS.IS]: new ConditionConfig({inputType:'select', options: user_authentication_type_options()}),
        [CONDITIONS.IS_NOT]: new ConditionConfig({inputType:'select', options: user_authentication_type_options()}),
      },
    }),
    new FieldConfig({
      name: 'user_email',
      label: 'User Email',
      conditions: {
        [CONDITIONS.EQUAL]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.NOT_EQUAL]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.CONTAINS]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.DOES_NOT_CONTAIN]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.BEGINS_WITH]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.DOES_NOT_BEGIN_WITH]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.ENDS_WITH]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.DOES_NOT_ND_WITH]: new ConditionConfig({inputType:'string'}),
        [CONDITIONS.IS_NULL]: new ConditionConfig({inputType: 'null'}),
        [CONDITIONS.IS_NOT_NULL]: new ConditionConfig({inputType: 'null'}),
      },
    }),
    new FieldConfig({
      type: 'date',
      name: 'user_expiration_date',
      label: 'User Expiration Date',
      conditions: {
        [CONDITIONS.EQUAL]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.NOT_EQUAL]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.LESS_THAN]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.LESS_THAN_EQUAL]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.GREATER_THAN]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.GREATER_THAN_EQUAL]: new ConditionConfig({inputType:'date'}),
        [CONDITIONS.IS_BETWEEN]: new ConditionConfig({inputType:'date_range'}),
        [CONDITIONS.IS_NOT_BETWEEN]: new ConditionConfig({inputType:'date_range'}),
      },
    }),
    new FieldConfig({
      type: 'select',
      name: 'project_purpose',
      label: 'Project Purpose',
      conditions: {
        [CONDITIONS.IS]: new ConditionConfig({inputType:'select', options: project_purpose_options()}),
        [CONDITIONS.IS_NOT]: new ConditionConfig({inputType:'select', options: project_purpose_options()}),
      },
    }),
  ];


