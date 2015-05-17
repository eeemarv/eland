<?php
echo '</div>';
echo '</div>';
echo '</div>';

/*
if ($s_accountrole == 'admin')
{
	echo '<div class="container-fluid">';
	echo '<div class="row">';
	echo '<div class="col-xs-12 bg-info">';
	echo '<p><b>Rapporteer bugs in de <a href="https://github.com/eeemarv/elas-heroku/issues">Github issue tracker</a>.</b> (Maak eerst een <a href="https://github.com">Github</a> account aan.)</p>';
	echo '</div></div></div>';
}


 <div id="footer">
	<div id="footerleft">
	<?php
	if(isset($s_id)){
		echo $s_name." (".trim($s_letscode)."), ";
		echo " <a href='".$rootpath."logout.php'>Uitloggen</a>";
	}
	?>
	</div>
	<div id="footerright">
	<a href="https://github.com/eeemarv/elas-heroku ">eLAS-Heroku</a>
	</div>
</div>**/

echo '<div class="clearfix"></div>';
echo '<div class="container-fluid">';
echo '<footer class="footer">';
echo '<p><a href="https://github.com/eeemarv/elas-heroku">eLAS-Heroku ';
echo '</a>&nbsp;<i class="fa fa-github"></i></p></footer>';
echo '</div>'; 

echo '<script src="' . $cdn_jquery . '"></script>';
echo '<script src="' . $cdn_bootstrap_js . '"></script>';
echo '<script src="' . $cdn_footable_js . '"></script>';
echo '<script src="' . $rootpath . 'js/base.js"></script>';
?>
    <!-- Menu Toggle Script -->
    <script>
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });
    </script>
<?php


if (isset($includejs))
{
	echo $includejs;
}

echo '</body>';
echo '</html>';
