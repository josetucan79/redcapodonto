export const useLogicTextArea = (htmlElement) => {
    const { openLogicEditor, logicSuggestSearchTip } = window
    if (!openLogicEditor || !logicSuggestSearchTip) {
        console.warn('Cannot initialize the Logic Text Area')
        return
    }

    const LogicTextArea = htmlElement
    LogicTextArea.addEventListener('click', (e) => {
        openLogicEditor(LogicTextArea)
        // logicSuggestSearchTip(LogicTextArea, e)

        // to work with modals, wait fot the dialog to show, then give it maximum z-index
        /* setTimeout(() => {
            const logicTextAreaDialog = document.querySelector('[aria-describedby="rc-ace-editor-dialog"].ui-dialog')
            const popover = document.querySelector('.popover.enter-logic-here-hint')
            if (logicTextAreaDialog) logicTextAreaDialog.style.zIndex = 9999
            if (popover) popover.style.zIndex = 9999
        }, 200) */
    })
    LogicTextArea.addEventListener('keydown', (e) => {
        logicSuggestSearchTip(LogicTextArea, e)
    })
}
