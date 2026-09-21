// Serialize the object and its methods to a string
export const serialize = (myObj) => JSON.stringify({
    ...myObj,
    __proto__: Object.getPrototypeOf(myObj),
});

// Parse the string back to an object with methods intact
export const deserialize = (serialized) => JSON.parse(serialized, (key, value) =>
    typeof value === "string" && value.includes("function")
    ? eval(`(${value})`)
    : value
);

export const instanceToBlob = (instance) => {
    // Serialize the instance as a string using JSON.stringify
    const serialized = serialize(instance);
    
    // Convert the string to a Uint8Array
    const uint8array = new TextEncoder().encode(serialized);
    
    // Create a Blob object from the Uint8Array
    const blob = new Blob([uint8array], {type: "application/octet-stream"});
    
    return blob;
}

export const blobToInstance = (blob) => {
    return new Promise((resolve, reject) => {
      // Create a FileReader object
      const reader = new FileReader();
      
      // When the FileReader has loaded the data, convert it to a string and deserialize
      reader.onload = () => {
        const contents = new TextDecoder().decode(reader.result);
        const deserialized = deserialize(contents);
        resolve(deserialized);
      };
      
      // When an error occurs, reject the Promise
      reader.onerror = () => {
        reject(reader.error);
      };
      
      // Read the contents of the Blob as a Uint8Array
      reader.readAsArrayBuffer(blob);
    });
  }
  


  function objectToBlob(obj) {
    // Clone the object using the structured clone algorithm
    const clonedObj = clone(obj);
    // Convert the cloned object to a Uint8Array
    const uint8array = new TextEncoder().encode(JSON.stringify(clonedObj));
    
    // Create a Blob from the Uint8Array
    const blob = new Blob([uint8array], {type: "application/octet-stream"});
    
    return blob;
  }

  async function blobToObject(blob) {
    // Read the contents of the Blob into a Uint8Array
    const blobReader = new FileReader();
    blobReader.readAsArrayBuffer(blob);
    await new Promise(resolve => {
      blobReader.onloadend = () => resolve();
    });
    const buffer = blobReader.result;
    const uint8array = new Uint8Array(buffer);
  
    // Decode the Uint8Array into a JSON string
    const json = new TextDecoder().decode(uint8array);
  
    // Parse the JSON string into the original object
    const obj = JSON.parse(json);
  
    // Return the original object
    return obj;
  }
  
  
  function clone(obj) {
    let clone = Object.assign(Object.create(Object.getPrototypeOf(obj)), obj)
    return clone
  }


export  {objectToBlob, blobToObject}