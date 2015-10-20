$(document).ready(function(){
    $("#id_submitbutton").click(function(event){
        event.preventDefault();

        var sday = $("#id_sessiondate_day").val();
        var smonth = $("#id_sessiondate_month").val();
        var syear = $("#id_sessiondate_year").val();
        var sdate = new Date(syear, smonth-1, sday);
        var sunixtime = sdate.getTime()/1000;

        $.ajax({type: "POST",
              url: M.cfg.wwwroot + "/mod/attendance/course_startdate_ajax.php",
              data: { courseid: $("input[name=courseid]").val() },
              success:function(result){
                  console.log(result);
                  if (sunixtime < result.coursestartdate) {
                      return confirm(result.confirmmessage);
                  }
                  return true;
              },
              error: function(){
                  alert('Failure');
                  return false;
              }
        });
    });
});
