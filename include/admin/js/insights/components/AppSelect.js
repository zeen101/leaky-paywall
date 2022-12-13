export default {
    template: `
        <select v-model="period" @change="change">
            <option value="today">Today</option>
            <option value="7 days">Last 7 days</option>
            <option value="4 weeks">Last 4 weeks</option>
            <option value="3 months">Last 3 months</option>
        </select>
    `,

   props: {
    period: String
   },

    methods: {
        change(event) {
            this.$emit('change', event.target.value);
        }
    }
}