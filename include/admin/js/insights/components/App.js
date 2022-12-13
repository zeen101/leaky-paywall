import AppStats from "./AppStats.js";
import AppSelect from "./AppSelect.js";
import Stat from "./Stat.js";
import NagConversions from "./NagConversions.js";

let App = {
    components: {
        'app-stats': AppStats,
        'app-select': AppSelect,
        'stat': Stat,
        'nag-conversions': NagConversions
    },
}

Vue.createApp(App).mount('#lp-wit-app');