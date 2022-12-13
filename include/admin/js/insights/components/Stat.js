export default {
    template: `
    <div class="card">
        <div class="card-title">{{ title }}</div>
        <div class="card-amount"><span>$</span>{{ number }}</div>
    </div>
    `,

    props: {
        title: String,
        number: String,
        period: String
    },
    
}