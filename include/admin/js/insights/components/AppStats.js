import AppSelect from "./AppSelect.js";
import Stat from "./Stat.js";

export default {
    components: {
        'app-select': AppSelect,
        'stat': Stat
    },
    template: `
        <h1>Leaky Paywall Insights</h1>

        <div class="insights-tabs">
            <a class="active" href="#">Insights</a> <a href="#">Subscriptions</a> <a href="#">Content</a>
        </div>

        <p>
            <select v-model="period" @change="change">
                <option value="today">Last 24 hours</option>
                <option value="7 days">Last 7 days</option>
                <option value="4 weeks">Last 4 weeks</option>
                <option value="3 months">Last 3 months</option>
            </select>
        </p>

        <div class="card-stats">

            <div class="card">
                <span class="dashicons dashicons-chart-bar"></span>
                <div class="card-content">
                    <div class="card-title">Total Revenue</div>
                    <div class="card-amount" 
                        :class="{
                        'is-loading': loading == true
                    }">{{ total_revenue }}</div>
                </div>  
                
            </div>

            <div class="card">
                <span class="dashicons dashicons-money-alt"></span>
                <div class="card-content">
                    <div class="card-title">New Paid Subscribers</div>
                    <div class="card-amount" :class="{
                        'is-loading': loading == true
                    }">{{ new_paid_subs }}</div>
                </div>
            </div>

            <div class="card">
                <span class="dashicons dashicons-admin-users"></span>
                <div class="card-content">
                    <div class="card-title">New Free Subscribers</div>
                    <div class="card-amount" :class="{
                        'is-loading': loading == true
                    }">{{ new_free_subs }}</div>
                </div>
            </div>

            <div class="card">
                <span class="dashicons dashicons-heart"></span>
                <div class="card-content">
                    <div class="card-title">New Gift Subscribers</div>
                    <div class="card-amount" :class="{
                        'is-loading': loading == true
                    }">{{ new_gift_subs }}</div>
                </div>
            </div>

        </div>

        <div>
            <h1>Top Content Leading to Conversion</h1>
            <p>Content the user was viewing when the nag was displayed and they clicked a "subscribe" link</p>
            
            <div class="content-coversions">
                <div class="content-conversion-list">
                    <h3>Paid</h3>
                    <ol :class="{
                        'is-loading': loading == true
                    }">
                        <li 
                            v-for="item in paid_content"
                            >{{ item }}</li>
                    </ol>
                </div>

                <div class="content-conversion-list">
                    <h3>Free</h3>
                    <ol :class="{
                        'is-loading': loading == true
                    }">
                        <li 
                            v-for="item in free_content"
                            >{{ item }}</li>
                    </ol>
                </div>
            </div>
        </div>

    `,

    data() {
        return {
            period: '4 weeks',
            total_revenue: '',
            new_paid_subs: '',
            new_free_subs: '',
            new_gift_subs: '',
            paid_content: [],
            free_content: [],
            // active_subs: [],
            loading: true
        }       
    },

    methods: {
        change(){
            this.loading = true;
            this.get_total_revenue();
            this.get_new_paid_subs();
            this.get_new_free_subs();
            this.get_new_gift_subs();
            this.get_paid_content();
            this.get_free_content();
            // this.get_active_subs();
        },
        get_total_revenue() {
            this.total_revenue = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                this.total_revenue = data.total_revenue;
                
            });
        },
        get_recurring_revenue() {
            this.recurring_revenue = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                this.recurring_revenue = data.number;
            });
        },
        get_new_paid_subs() {
            this.new_paid_subs = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                this.new_paid_subs = data.new_paid_subs;
            });
        },
        get_new_free_subs() {
            this.new_free_subs = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                this.new_free_subs = data.new_free_subs;
            });
        },
        get_new_gift_subs() {
            this.new_gift_subs = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                this.new_gift_subs = data.new_gift_subs;
            });
        },
        get_paid_content() {
            this.paid_content = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
             data.append( 'period', this.period );
    
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
            this.free_content = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );
    
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

                this.loading = false;
            });
        },

        get_active_subs() {
            this.active_subs = '';

            const data = new FormData();
            data.append( 'action', 'lp_reports_get_data' );
            data.append( 'period', this.period );

            fetch(lp_wit_ajax.ajax_url, {
                method: "POST",
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                
                this.active_subs = data.active_subs;

                console.log('active subs');
                console.log(this.active_subs);

                this.loading = false;
            });
        }
    },

    created() {
        this.get_total_revenue();
        this.get_new_paid_subs();
        this.get_new_free_subs();
        this.get_new_gift_subs();
        this.get_paid_content();
        this.get_free_content();
        // this.get_active_subs();
    }

    

}