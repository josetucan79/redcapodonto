const useValidationManager = () => {
    let errors = {}

    return {
        addError(propertyName, error) {
            const message = error?.message ?? error
            if (!(propertyName in errors)) errors[propertyName] = []
            errors[propertyName].push(message)
        },
        hasErrors() {
            const found = Object.keys(errors).find(
                (_fieldErrors) => _fieldErrors?.length > 0
            )
            return found ? true : false
        },
        errors: () => errors,
    }
}

/**
 * Creates a validation handler based on the provided rules.
 *
 * @param {Object} rules - An object containing validation rules. Each key in this object
 *                         represents a field to be validated. The value for each key is
 *                         a callable function that performs validation for that field.
 *                         The callable should return `true` if the field data is valid,
 *                         or it should throw an error if the validation fails.
 * @returns {Function} A validation function based on the specified rules.
 *
 * @example
 * // Example of a rules object:
 * const validationRules = {
 *   username: (value) => {
 *     if (!value || value.length < 3) {
 *       throw new Error("Username must be at least 3 characters long");
 *     }
 *     return true;
 *   },
 *   email: (value) => {
 *     const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
 *     if (!emailRegex.test(value)) {
 *       throw new Error("Invalid email format");
 *     }
 *     return true;
 *   }
 * };
 *
 * // Usage of the useValidation function with the defined rules:
 * const validate = useValidation(validationRules);
 * try {
 *   validate({ username: "user", email: "user@example.com" });
 *   // Proceed if validation passes
 * } catch (error) {
 *   // Handle validation error
 * }
 */
export const useValidation = (rules) => {
    const validate = (data) => {
        const validationManager = useValidationManager()

        for (const [key, keyRules] of Object.entries(rules)) {
            for (const rule of keyRules) {
                if (typeof rule !== 'function') {
                    console.warn(
                        `the provided rule ${rule} is not a valid function`
                    )
                    continue
                }
                try {
                    const valid = rule(data?.[key])
                } catch (error) {
                    validationManager.addError(key, error)
                }
            }
        }

        return validationManager
    }

    return validate
}

/**
 * built in validators
 */

export const required = (props) => {
    const message = props?.message ?? `this value is required`
    return (value) => {
        if (typeof value === 'string') {
            value = value.trim() // Trim whitespace for string values
            if (value.length < 1) throw new Error(message)
        }
        if (Array.isArray(value)) {
            if (value.length === 0) throw new Error(message) // Empty arrays are invalid
        }
        if (value instanceof Set || value instanceof Map) {
            if (value.size === 0) throw new Error(message) // Empty Sets or Maps are invalid
        }
        if (typeof value === 'object' && value !== null) {
            if (Object.keys(value).length === 0) throw new Error(message) // Objects with no properties are invalid
        }
        if (!value) throw new Error(message) // Falsy values are invalid
    }
}

export const contains = (array = [], props) => {
    const message =
        props?.message ??
        `this value must be one of these: '${array.join(', ')}`
    return (value) => {
        if (!array.includes(value)) throw new Error(message)
    }
}

export const isTrue = (props) => {
    const message = props?.message ?? `this value must be 'true'`
    return (value) => {
        if (value !== true) throw new Error(message)
    }
}

export const isFalse = (props) => {
    const message = props?.message ?? `this value must be 'false'`
    return (value) => {
        if (value !== false) throw new Error(message)
    }
}

/**
 * execute all provided validators and return only the first not being valid.
 * this will stop as soon as the first validators fails.
 * 
 * @param {Array} validators 
 * @throws {Error} 
 */
export const firstError = (validators = []) => {
    return (value) => {
        for (const validator of validators) {
            validator(value)
        }
    }
}

/**
 * Validates that the value is at least the specified minimum.
 *
 * @param {number} min - The minimum value allowed.
 * @param {Object} [props] - Optional properties, including a custom error message.
 * @returns {Function} A validator function that throws an error if validation fails.
 */
export const isMin = (min, props) => {
    const message =
        props?.message ?? `this value must be at least ${min}`;
    return (value) => {
        if (typeof value !== 'number') {
            throw new Error(`this value must be a number`);
        }
        if (value < min) {
            throw new Error(message);
        }
    };
};

/**
 * Validates that the value is at most the specified maximum.
 *
 * @param {number} max - The maximum value allowed.
 * @param {Object} [props] - Optional properties, including a custom error message.
 * @returns {Function} A validator function that throws an error if validation fails.
 */
export const isMax = (max, props) => {
    const message =
        props?.message ?? `this value must be at most ${max}`;
    return (value) => {
        if (typeof value !== 'number') {
            throw new Error(`this value must be a number`);
        }
        if (value > max) {
            throw new Error(message);
        }
    };
};

/**
 * Validates that the value is between the specified minimum and maximum values.
 * This validator uses both `isMin` and `isMax` internally.
 *
 * @param {number} min - The minimum value allowed.
 * @param {number} max - The maximum value allowed.
 * @param {Object} [props] - Optional properties, including a custom error message.
 * @returns {Function} A validator function that throws an error if validation fails.
 */
export const isBetween = (min, max, props) => {
    // Prepare a default message if one is not provided.
    const defaultMessage = `this value must be between ${min} and ${max}`;
    // Use the provided custom message if available, else use the default.
    const message = props?.message ?? defaultMessage;

    // Create the individual validators.
    // Note: We wrap the custom message for each individual validator to be consistent.
    const minValidator = isMin(min, { message });
    const maxValidator = isMax(max, { message });

    return (value) => {
        // Run the min and max validators sequentially.
        // Each will throw an error if the value does not meet the requirement.
        minValidator(value);
        maxValidator(value);
    };
};

