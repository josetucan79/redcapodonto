

const CdpIDKeyListener = function(identifier_key_field_name, callback) {
  let executed = false
  this.identifier_key_field_name = null

  this.constructor = () => {
    this.identifier_key_field_name = identifier_key_field_name
    this.callback = callback
    console.log(identifier_key_field_name, executed)
    this.setListener()
  }

  this.setListener = () => {
    if(this.identifier_key_field_name.trim()=='') {
      console.warn('no identifier for a field has been provided')
      return
    }
    const element = document.querySelector(`#questiontable input[name="${this.identifier_key_field_name}"]`)
    if(!element) return
    element.addEventListener('change', event => {
      const identifier = element.value
      if(typeof this.callback==='function') {
        this.callback(identifier)
      }
    })
  }

  //execute the constructor
  this.constructor.apply(this, arguments)
}