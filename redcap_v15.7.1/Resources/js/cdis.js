

export default class CDIS {
    _version = '1.0.0'

    construct() {}

    get version() {
        return this._version
    }

    disconnectEhrUser(ehr_user) {
        const disconnect = confirm(`Your REDCap user is mapped to the EHR user '${ehr_user}'.
            The mapping allows REDCap to auto-login during a launch from EHR.
            Do you want to remove this mapping?
            You can create a new mapping the next time you perform a launch to get an access token.`)
        if(!disconnect) return
    }
}