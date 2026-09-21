// Create function called “arrayDiff” that takes two arrays as arguments, “array1” and “array2.”
const arrayDiff = (array1, array2) => {
    // Handle edge cases: (a) If the length of “array1” is 0, return an empty array
    if(array1.length === 0){return []}
    // Handle edge cases: (b) If the length of “array2” is 0, return “array1”
    if(array2.length === 0){return array1}
    
    
    // Create the array to return called “returnArray”
    let returnArray = [];
    // Loop through “array1”
    array1.forEach((element) => {
        // If the element is not included in “array2”, push it to “returnArray”
        if(!array2.includes(element)){ returnArray.push(element) }
    })

    return returnArray;
}

const arrayIntersect = (array1, array2) => {
    const intersection = array1.filter(value => array2.includes(value));
    return intersection
}

const arrayIntersected = (array1, array2) => {
    const intersection = arrayIntersect(array1, array2)
    return intersection.length>0
}

const arrayUnion = (array1, array2) => {
    const returnArray = [...array1]
    array2.forEach(element => {
        if(returnArray.indexOf(element)<0) returnArray.push(element)
    })
    return returnArray
}

export { arrayDiff, arrayIntersect, arrayIntersected, arrayUnion }