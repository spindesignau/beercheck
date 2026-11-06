/** Minimal accessible typeahead bound to a hidden id input. */
export function typeahead({ url, initialId = '', initialLabel = '', extraParams = () => ({}) }) {
    return {
        query: initialLabel,
        selectedId: initialId,
        results: [],
        open: false,
        active: -1,

        async search() {
            this.selectedId = '';
            if (this.query.trim().length < 2) {
                this.results = [];
                return;
            }
            const params = new URLSearchParams({ q: this.query, ...extraParams() });
            const { data } = await window.axios.get(url + '?' + params);
            this.results = data;
            this.open = true;
            this.active = -1;
        },

        choose(item) {
            this.selectedId = item.id;
            this.query = item.label;
            this.open = false;
        },

        move(delta) {
            if (!this.results.length) return;
            this.active = (this.active + delta + this.results.length) % this.results.length;
        },

        enter() {
            if (this.active >= 0) this.choose(this.results[this.active]);
        },
    };
}
