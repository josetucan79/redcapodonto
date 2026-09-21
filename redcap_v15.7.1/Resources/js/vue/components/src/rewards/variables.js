export const REVIEW_STATUS = Object.freeze({
    APPROVED: 'approved',
    INELIGIBLE: 'ineligible',
    PENDING: 'pending',
    REJECTED: 'rejected',
})

export const ACTION_STAGE = Object.freeze({
    ELIGIBILITY_REVIEW: 'eligibility_review',
    FINANCIAL_AUTHORIZATION: 'financial_authorization',
    COMPENSATION_DELIVERY: 'compensation_delivery',
})
export const ACTION_EVENT = Object.freeze({
    REVIEWER_APPROVAL: 'reviewer:approval',
    REVIEWER_REJECTION: 'reviewer:rejection',
    REVIEWER_RESTORE: 'reviewer:restore',
    BUYER_APPROVAL: 'buyer:approval',
    BUYER_REJECTION: 'buyer:rejection',
    PLACE_ORDER: 'place_order',
    SEND_EMAIL: 'send_email',
    REVERT: 'revert',
})
export const ACTION_STATUS = Object.freeze({
    PENDING: 'pending',
    COMPLETED: 'completed',
    ERROR: 'error',
    UNKNOWN: 'unknown',
})

export const ORDER_STATUS = Object.freeze({
    INVALID: 'invalid',
    ELIGIBLE: 'eligible',
    INELIGIBLE: 'ineligible',
    PENDING: 'pending',
    REVIEWER_APPROVED: 'reviewer:approved',
    REVIEWER_REJECTED: 'reviewer:rejected',
    BUYER_APPROVED: 'buyer:approved',
    BUYER_REJECTED: 'buyer:rejected',
    ORDER_PLACED: 'order:placed',
    COMPLETED: 'completed',
    SCHEDULED: 'scheduled',
    PROCESSING: 'processing',
    CANCELED: 'canceled',
    ERROR: 'error',
    UNKNOWN: 'unknown',
})

export const ENABLED_STATUS_LIST = [
    ORDER_STATUS.COMPLETED,
    ORDER_STATUS.ELIGIBLE,
    ORDER_STATUS.BUYER_APPROVED,
    ORDER_STATUS.BUYER_REJECTED,
    ORDER_STATUS.REVIEWER_APPROVED,
    ORDER_STATUS.REVIEWER_REJECTED,
    ORDER_STATUS.SCHEDULED,
    ORDER_STATUS.ORDER_PLACED,
]

export const PROGRESS_STATUS = Object.freeze({
    PENDING: 'pending',
    COMPLETED: 'completed',
    REJECTED: 'rejected',
})
