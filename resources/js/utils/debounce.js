/**
 * Minimal trailing-edge debounce.
 *
 * Avoids pulling in lodash for a single helper.
 */
export function debounce(fn, wait = 300) {
    let timeout

    const debounced = (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => fn(...args), wait)
    }

    debounced.cancel = () => clearTimeout(timeout)

    return debounced
}
