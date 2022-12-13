export default {
    template: `
        <h3>Paid</h3>
        <ol>
            <li v-for="item in paid_content">{{ item }}</li>
        </ol>

        <h3>Free</h3>
        <ol>
            <li v-for="item in free_content">{{ item }}</li>
        </ol>
    `,

    data() {
        return {
            paid_content: [
               
            ],
            free_content: [
               
            ]
        }
    },

    methods: {
        get_paid_content() {
            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', '4 weeks' );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                
                this.paid_content = data.paid_content;

                console.log('paid content');
                console.log(this.paid_content);
            });
        },

        get_free_content() {
            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', '4 weeks' );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                
                this.free_content = data.free_content;

                console.log('free content');
                console.log(this.free_content);
            });
        }
    },

    created() {
        this.get_paid_content();
        this.get_free_content();
    }
}