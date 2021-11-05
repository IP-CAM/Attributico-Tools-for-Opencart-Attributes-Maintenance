export default function toolsEvents() {
    /**
         * Alerts for tools when tasks is running
         * Placed in div = column-2, because column-1 is vtabs
         **/
    $('a[data-toggle="pill"]').on('click', function (e) {
        $("#column-2 .alert-success").hide();
        $("#column-2 .task-complete").hide();
        $("#column-2 .alert-info").show();
    });

    /**
    * Tools filter toggle manage
    * Needs data-filter attribute, for example data-filter=data-filter='["group", "category"]'
    **/
    $("#column-1 li, #column-2 #tabs-standart li").on('click', function () {
        if (!!this.dataset.filter) {
            let activeFilter = JSON.parse(this.dataset.filter);
            $("#column-2 #filter-tools").removeAttr('hidden')
            // Turn off all filter-tools columns      
            $('#column-2 [class *= "filter-tools-"]').attr('hidden', 'hidden')
            activeFilter.map((element, index) => {
                // Turn on needed columns
                $("#column-2 td.filter-tools-" + element).removeAttr('hidden')
            })
        } else {
            // Turn off whole filter's table zone
            $("#column-2 #filter-tools").attr('hidden', 'hidden')            
        }
    })

    // access to change case radio in toopls  
    $('input[name ^= "case_"]:checkbox').on('change', function (e) {
        let flag = $(this).is(":checked");
        let target = $(this).data('target');            
        $(`input[name="${target}"]`).each(function (indx, element) {
             $(element).prop({
                 "disabled": !flag,
                 "checked": flag ? (indx == 0 ? "checked" : "") : ""
             })
         })
     })

}