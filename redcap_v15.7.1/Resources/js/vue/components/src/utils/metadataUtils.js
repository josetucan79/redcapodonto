export class Container {
    constructor(name, parent = null) {
        this.name = name;
        // this.parent = () => parent;
        this.children = [];
    }
}

// Define the Element class
export class Element {
    constructor(data, parent = null) {
        this.data = data; // Original item data
        // this.parent = () => parent;
    }
}

// Function to serialize the container for JSON output
export function serializeContainer(container) {
    const result = {
        name: container.name,
        children: container.children.map(child => {
            if (child instanceof Container) {
                return serializeContainer(child);
            } else if (child instanceof Element) {
                return child.data;
            }
        })
    };
    return result;
}

// Function to transform the input list
export function groupMetadata(input, max=-1) {
    // Create the root container
    const root = new Container("", null)

    // Map to keep track of categories and subcategories
    const categoryMap = {}

    let counter = 0
    for (const key in input) {
        counter++
        if (max >= 0 && counter >= max) break
        const item = input[key]
        const category = item.category && item.category.trim()
        const subcategory = item.subcategory && item.subcategory.trim()

        if (!category) {
            // No category: add item directly to root
            const element = new Element(item, root)
            root.children.push(element)
        } else {
            // Category exists
            // Check if the category container already exists
            let categoryContainer = categoryMap[category]
            if (!categoryContainer) {
                // Create new category container
                categoryContainer = new Container(category, root)
                categoryMap[category] = categoryContainer
                root.children.push(categoryContainer)
            }

            if (!subcategory) {
                // No subcategory: add item to category container
                const element = new Element(item, categoryContainer)
                categoryContainer.children.push(element)
            } else {
                // Subcategory exists
                // Check if the subcategory container exists under the category
                let subcategoryContainer = categoryContainer.children.find(
                    child => child instanceof Container && child.name === subcategory
                )
                if (!subcategoryContainer) {
                    // Create new subcategory container
                    subcategoryContainer = new Container(subcategory, categoryContainer)
                    categoryContainer.children.push(subcategoryContainer)
                }
                // Add item to subcategory container
                const element = new Element(item, subcategoryContainer)
                subcategoryContainer.children.push(element)
            }
        }
    }

    return root;
}