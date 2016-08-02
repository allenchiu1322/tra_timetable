<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>timetable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo(base_url()); ?>css/bootstrap.min.css" rel="stylesheet" media="screen">
    <link href="<?php echo(base_url()); ?>css/bootstrap-responsive.min.css" rel="stylesheet" media="screen">
	<style type="text/css">
    .fixthis {
        position:fixed;
        left:20px;
        top:20px;
        display: none;
    }
	</style>
</head>
<body>
<div class="navbar">
<div class="navbar-inner">
<a class="brand" href="#">animepagehelper</a>
<ul class="nav">
<li class="active hidden-phone"><a href="#">index</a></li>
<li><a href="<?php echo(site_url()); ?>/timetable/test">測試</a></li>
</ul>
</div>
</div>
<div id="alert" class="alert fixthis" style="">
<strong>訊息:</strong><span id="message_out">....</span>
<a href="#" class="close">&times;</a>
</div>
<div class="container-fluid">
<div class="row-fluid">
<div id="step1" class="well span3">
</div>
<div id="step2" class="well span9">
</div>
</div>
<div class="row-fluid">
<div id="step3" class="span6 well">
</div>
<div id="step4" class="span6 well">
</div>
</div>
</div>
<script src="<?php echo(base_url()); ?>js/jquery-1.10.2.min.js" type="text/javascript"></script>
<script src="<?php echo(base_url()); ?>js/jquery.cookie.js" type="text/javascript"></script>
<script type="text/javascript">
</script>
<script src="<?php echo(base_url()); ?>js/bootstrap.min.js"></script>
</body>
</html>
