import {useModal} from '../../Resources/js/Composables/index.es.js.php'

const modal = useModal();

export default class PreviewHandler {
    async run(templateElementSelector) {
        const templateElement = document.querySelector(templateElementSelector);
        if (!templateElement) {
            console.error('The selector is invalid or the element does not exist.');
            return;
        }

        const templateValue = templateElement.value ?? false;

        if (!templateValue) {
            console.error('The input value is empty.');
            return;
        }

        const params = new URLSearchParams({
            pid: window.pid,
            redcap_csrf_token: window.redcap_csrf_token,
            route: 'RewardsController:getEmailPreview',
        });

        const body = {
            template: templateValue,
            record_id: null,
            event_id: null,
        };

        try {
            const baseURL = `${window.app_path_webroot_full}redcap_v${window.redcap_version}/`
            const response = await fetch(`${baseURL}?${params.toString()}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Response received:', result, result.preview);
            const promise = modal.show({
                title: 'Preview',
                body: result.preview ?? 'no preview',
                cancelText: null,
                okText: 'Close',
            });
        } catch (error) {
            console.error('Error during the POST request:', error);
        }
    }
}