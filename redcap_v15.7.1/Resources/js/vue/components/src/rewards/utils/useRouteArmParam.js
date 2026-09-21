import { computed } from 'vue'
import { useRoute } from 'vue-router'

export default () => {
    const route = useRoute()
    const arm_num = computed(() => {
        const arm_num = route?.params?.arm_num ?? 1
        const validated_arm_num =
        arm_num === '' || arm_num == null ? 1 : arm_num
        return validated_arm_num
    })
    return arm_num
}
