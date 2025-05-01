<?php
//phpunit.readthedocs.io/en/8.0/writing-tests-for-phpunit.html

use PHPUnit\Framework\TestCase;
use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;


class Proj4phpTest extends TestCase
{


    public function testEPSG3418()
    {

        $proj4 = new Proj4php();
        $progWGS84  = new Proj('WGS84', $proj4);
        $proj3418 = new Proj(
            'PROJCS["NAD_1983_StatePlane_Iowa_South_FIPS_1402_Feet",GEOGCS["GCS_North_American_1983",DATUM["D_North_American_1983",SPHEROID["GRS_1980",6378137.0,298.257222101]],PRIMEM["Greenwich",0.0],UNIT["Degree",0.0174532925199433]],PROJECTION["Lambert_Conformal_Conic"],PARAMETER["False_Easting",1640416.6667],PARAMETER["False_Northing",0.0],PARAMETER["Central_Meridian",-93.5],PARAMETER["Standard_Parallel_1",41.7833333333333],PARAMETER["Standard_Parallel_2",40.6166666666667],PARAMETER["Latitude_Of_Origin",40.0],UNIT["US survey foot",0.304800609601219]]',
            $proj4
        );
        $pointSrc = new Point(1623863.8131117225, 643763.90620113909);
        $pointDest = $proj4->transform($proj3418, $progWGS84, $pointSrc);
        $this->assertEqualsWithDelta(-93.560676, $pointDest->x, 0001);
        $this->assertEqualsWithDelta(41.766901, $pointDest->y, .0001);



	    $proj4 = new Proj4php();
        $progWGS84  = new Proj('WGS84', $proj4);
        $proj3418 = new Proj('+proj=lcc +lat_0=40 +lon_0=-93.5 +lat_1=41.7833333333333 +lat_2=40.6166666666667 +x_0=500000.00001016 +y_0=0 +datum=NAD83 +units=us-ft +no_defs +type=crs',$proj4);
        $pointSrc = new Point( 1623863.8131117225, 643763.90620113909);
        $pointDest = $proj4->transform($proj3418, $progWGS84, $pointSrc);
        $this->assertEqualsWithDelta(-93.560676, $pointDest->x, 0001);
        $this->assertEqualsWithDelta(41.766901, $pointDest->y, .0001);

    }
    public function testIssue87()
    {
        $this->expectNotToPerformAssertions();
	$proj4 = new Proj4php();

	$proj4->addDef("LONG_LAT", '+proj=longlat +ellps=WGS84 +datum=WGS84');
        $proj4->addDef("ALBERS_CONICOL", '+proj=aea +lat_1=-29.9 +lat_2=-36. +lat_0=-32.95 +lon_0=117.55 +a=6378137 +b=6356752.31414');

        // Create two different projections.
        $projLongLat  = new Proj('LONG_LAT', $proj4);
        $projAlbers = new Proj('ALBERS_CONICOL',$proj4);

        // Create a point.
        $pointSrc = new Point( 119.414059784227, -30.6792349303822, $projLongLat);
        // "Source: " . $pointSrc->toShortString() . " in LongLat  <br>";

        // Transform the point between datums.
        $pointDest = $proj4->transform($projLongLat, $projAlbers, $pointSrc);

	 // Expected  X : 178.5
         // Expected  Y : 250.5
    }

    public function testTransform()
    {
        $this->expectNotToPerformAssertions();
        $proj4     = new Proj4php();

        $projL93   = new Proj('EPSG:2154', $proj4);
        $projWGS84 = new Proj('EPSG:4326', $proj4);
        $projLI    = new Proj('EPSG:27571', $proj4);
        $projLSud  = new Proj('EPSG:27563', $proj4);
        $projL72   = new Proj('EPSG:31370', $proj4);
        $proj25833 = new Proj('EPSG:25833', $proj4);
        $proj31468 = new Proj('EPSG:31468', $proj4);
        $proj5514  = new Proj('EPSG:5514', $proj4);
        $proj28992 = new Proj('EPSG:28992', $proj4);
        $projCassini = new Proj('EPSG:28191', $proj4);
        $projlaea  = new Proj('+proj=laea +lat_0=90 +lon_0=0 +x_0=0 +y_0=0 +datum=WGS84 +units=m +no_defs', $proj4);
        $proj3825 = new Proj('EPSG:3825', $proj4); //TWD97 / TM2 zone 119
        $proj3826 = new Proj('EPSG:3826', $proj4); //TWD97 / TM2 zone 121
        $proj3827 = new Proj('EPSG:3827', $proj4); //TWD67 / TM2 zone 119
        $proj3828 = new Proj('EPSG:3828', $proj4); //TWD67 / TM2 zone 121
        $proj27700 = new Proj('+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs',$proj4);
        $proj27700bis = new Proj(' PROJCS["OSGB 1936 / British National Grid",GEOGCS["OSGB 1936",DATUM["D_OSGB_1936",SPHEROID["Airy_1830",6377563.396,299.3249646]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]],PROJECTION["Transverse_Mercator"],PARAMETER["latitude_of_origin",49], PARAMETER["central_meridian", -2], PARAMETER["scale_factor", 0.9996012717], PARAMETER["false_easting", 400000], PARAMETER["false_northing",-100000], UNIT["Meter",1]]', $proj4);


        // GPS
        // latitude        longitude
        // 48,831938       2,355781
        // 48°49'54.977''  2°21'20.812''
        //
        // L93
        // 652709.401   6859290.946
        //
        // LI
        // 601413.709   1125717.730
        //

        $pointSrc  = new Point('652709.401', '6859290.946', $projL93);
        $pointDest = $proj4->transform($projWGS84, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projLSud, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projWGS84, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projLI, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projL93, $pointSrc);

        $pointSrc  = new Point('177329.253543', '58176.702191');
        $pointDest = $proj4->transform($projL72, $projWGS84, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projWGS84, $projL72, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projL72, $proj25833, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($proj25833, $projWGS84, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projWGS84, $proj31468, $pointSrc);

        $pointSrc  = new Point('-868208.53', '-1095793.57');
        $pointDest = $proj4->transform($proj5514, $projWGS84, $pointSrc);

        $pointSrc  = $pointDest;
        $pointDest = $proj4->transform($projWGS84, $proj5514, $pointSrc);

        $pointSrc = new Point('148312.237261','457869.280549');
        $pointDest = $proj4->transform($proj28992,$projWGS84,$pointSrc);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projWGS84,$proj28992,$pointSrc);

        $pointSrc = new Point('317571.670235','78079.744775');
        $pointDest = $proj4->transform($projCassini,$projWGS84,$pointSrc);

        $pointSrc = new Point('-755703.994303','-704542.847453');
        $pointDest = $proj4->transform($projlaea,$projWGS84,$pointSrc);

        // TWD97 / TM2 zone 119
        $pointSrc = new Point('181688.209','2705952.753');
        $pointDest = $proj4->transform($proj3825,$projWGS84,$pointSrc);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projWGS84,$proj3825,$pointSrc);

        // TWD97 / TM2 zone 121
        $pointSrc = new Point('248170.787','2652129.936');
        $pointDest = $proj4->transform($proj3826,$projWGS84,$pointSrc);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projWGS84,$proj3826,$pointSrc);

        // TWD67 / TM2 zone 119
        $pointSrc = new Point('170900', '2701500');
        $pointDest = $proj4->transform($proj3827,$projWGS84,$pointSrc);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projWGS84,$proj3827,$pointSrc);

        // TWD67 / TM2 zone 121
        $pointSrc = new Point('247342.198','2652335.851');
        $pointDest = $proj4->transform($proj3828,$projWGS84,$pointSrc);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projWGS84,$proj3828,$pointSrc);

//        Proj4php::setDebug(true);
        $pointSrc = new Point('518060.14114953','175166.25201076');
        $pointDest = $proj4->transform($proj27700,$projWGS84,$pointSrc);

        $pointSrc = new Point('518060.14114953','175166.25201076');
        $pointDest = $proj4->transform($proj27700bis,$projWGS84,$pointSrc);

//        Proj4php::setDebug(false);
    }

    /**
     */
    public function testParseInlineWKTCode()
    {
        $proj4 = new Proj4php();

        //for lcc these are the public variables that should completley define the projection.
        $compare=array( 'lat0'=>'', 'lat1'=>'', 'lat2'=>'', 'k0'=>'', 'a'=>'',  'b'=>'', 'e'=>'', 'title'=>'', 'long0'=>'', 'x0'=>'', 'y0'=>'');

        $proj4->addDef('EPSG:32040', '+proj=lcc +lat_1=28.38333333333333 +lat_2=30.28333333333333 +lat_0=27.83333333333333 +lon_0=-99 +x_0=609601.2192024384 +y_0=0 +ellps=clrk66 +datum=NAD27 +to_meter=0.3048006096012192 +no_defs');

        $projNAD27Inline = new Proj('PROJCS["NAD27 / Texas South Central",GEOGCS["NAD27",DATUM["North_American_Datum_1927",SPHEROID["Clarke 1866",6378206.4,294.9786982138982,AUTHORITY["EPSG","7008"]],AUTHORITY["EPSG","6267"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4267"]],UNIT["US survey foot",0.3048006096012192,AUTHORITY["EPSG","9003"]],PROJECTION["Lambert_Conformal_Conic_2SP"],PARAMETER["standard_parallel_1",28.38333333333333],PARAMETER["standard_parallel_2",30.28333333333333],PARAMETER["latitude_of_origin",27.83333333333333],PARAMETER["central_meridian",-99],PARAMETER["false_easting",2000000],PARAMETER["false_northing",0],AUTHORITY["EPSG","32040"],AXIS["X",EAST],AXIS["Y",NORTH]]',$proj4);
        $projNAD27=new Proj('EPSG:32040', $proj4);

        $this->assertEqualsWithDelta(array_intersect_key(get_object_vars($projNAD27), $compare), array_intersect_key(get_object_vars($projNAD27Inline), $compare), 1e-10);

        //$proj4->addDef("EPSG:31370","+proj=lcc +lat_1=51.16666723333333 +lat_2=49.8333339 +lat_0=90 +lon_0=4.367486666666666 +x_0=150000.013 +y_0=5400088.438 +ellps=intl +towgs84=106.869,-52.2978,103.724,-0.33657,0.456955,-1.84218,1 +units=m +no_defs");
        $projBelge72Inline = new Proj('PROJCS["Belge 1972 / Belgian Lambert 72",GEOGCS["Belge 1972",DATUM["Reseau_National_Belge_1972",SPHEROID["International 1924",6378388,297,AUTHORITY["EPSG","7022"]],TOWGS84[106.869,-52.2978,103.724,-0.33657,0.456955,-1.84218,1],AUTHORITY["EPSG","6313"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4313"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],PROJECTION["Lambert_Conformal_Conic_2SP"],PARAMETER["standard_parallel_1",51.16666723333333],PARAMETER["standard_parallel_2",49.8333339],PARAMETER["latitude_of_origin",90],PARAMETER["central_meridian",4.367486666666666],PARAMETER["false_easting",150000.013],PARAMETER["false_northing",5400088.438],AUTHORITY["EPSG","31370"],AXIS["X",EAST],AXIS["Y",NORTH]]',$proj4);
        $projBelge72 = new Proj('EPSG:31370',$proj4);

        $this->assertEqualsWithDelta(array_intersect_key(get_object_vars($projBelge72), $compare), array_intersect_key(get_object_vars($projBelge72Inline), $compare), 1e-10);

        $proj4::$wktProjections["Lambert_Conformal_Conic"] = "lcc";
        $projL93Inline = new Proj('PROJCS["RGF93 / Lambert-93",GEOGCS["RGF93",DATUM["D_RGF_1993",SPHEROID["GRS_1980",6378137,298.257222101]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]],PROJECTION["Lambert_Conformal_Conic"],PARAMETER["standard_parallel_1",49],PARAMETER["standard_parallel_2",44],PARAMETER["latitude_of_origin",46.5],PARAMETER["central_meridian",3],PARAMETER["false_easting",700000],PARAMETER["false_northing",6600000],UNIT["Meter",1]]', $proj4);
        $projL93 = new Proj('EPSG:2154', $proj4);

        $this->assertEquals(array_intersect_key(get_object_vars($projL93), $compare), array_intersect_key(get_object_vars($projL93Inline), $compare));

        // for wgs84, points are lat/lng, so both functions return the input (identity transform)
        $projWGS84Inline      = new Proj('GEOGCS["GCS_WGS_1984",DATUM["D_WGS_1984",SPHEROID["WGS_1984",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]]', $proj4);
        $projWGS84       = new Proj('EPSG:4326', $proj4);

        $this->assertEquals('proj4php\LongLat', (get_class($projWGS84Inline->projection)));
        $this->assertEquals('proj4php\LongLat', (get_class($projWGS84->projection)));

// Need to compare at 0.1 only
//        $compare=array('b2'=>'', 'b'=>'', 'ep2'=>'', 'e'=>'', 'es'=>'');

        $proj4->addDef("EPSG:27700",'+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs');
        $projOSGB36Inline = new Proj('PROJCS["OSGB 1936 / British National Grid",GEOGCS["OSGB 1936",DATUM["D_OSGB_1936",SPHEROID["Airy_1830",6377563.396,299.3249646]],PRIMEM["Greenwich",0],UNIT["Degree",0.017453292519943295]],PROJECTION["Transverse_Mercator"],PARAMETER["latitude_of_origin",49],PARAMETER["central_meridian",-2],PARAMETER["scale_factor",0.9996012717],PARAMETER["false_easting",400000],PARAMETER["false_northing",-100000],UNIT["Meter",1]]',$proj4);
        $projOSGB36 = new Proj('EPSG:27700',$proj4);
//        $this->assertEquals(array_intersect_key(get_object_vars($projOSGB36), $compare), array_intersect_key(get_object_vars($projOSGB36Inline), $compare));

        //$projLI          = new Proj('EPSG:27571', $proj4);
        //$projLSud        = new Proj('EPSG:27563', $proj4);
    }

     /**
     * @runInSeparateProcess
     * TODO is this valuable?
     */
     public function testParseInlineProj4Code()
     {
         $this->expectNotToPerformAssertions();
        $proj4 = new Proj4php();
        $proj4->addDef("EPSG:27700",'+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs');

        $proj27700Inline=new Proj('+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs', $proj4);
        $proj27700=new Proj('EPSG:27700', $proj4);

        //$this->assertEquals(array($proj27700),get_object_vars($proj27700Inline));
    }

    public function testInlineProjectionMethod1()
    {
        $proj4 = new Proj4php();
        $proj4->addDef("EPSG:27700",'+proj=tmerc +lat_0=49 +lon_0=-2 +k=0.9996012717 +x_0=400000 +y_0=-100000 +ellps=airy +datum=OSGB36 +units=m +no_defs');
        $proj4->addDef("EPSG:31370","+proj=lcc +lat_1=51.16666723333333 +lat_2=49.8333339 +lat_0=90 +lon_0=4.367486666666666 +x_0=150000.013 +y_0=5400088.438 +ellps=intl +towgs84=106.869,-52.2978,103.724,-0.33657,0.456955,-1.84218,1 +units=m +no_defs");
        $proj4->addDef("EPSG:32040",'+proj=lcc +lat_1=28.38333333333333 +lat_2=30.28333333333333 +lat_0=27.83333333333333 +lon_0=-99 +x_0=609601.2192024384 +y_0=0 +ellps=clrk66 +datum=NAD27 +to_meter=0.3048006096012192 +no_defs ');

        $projWGS84  = new Proj('EPSG:4326', $proj4);
        $projOSGB36 = new Proj('EPSG:27700',$proj4);
        $projLCC2SP = new Proj('EPSG:31370',$proj4);
        $projNAD27  = new Proj('EPSG:32040',$proj4);

        $pointWGS84 = new Point(-96,28.5, $projWGS84);
        $pointNAD27 = $proj4->transform($projNAD27,$pointWGS84);
        $this->assertEqualsWithDelta($pointNAD27->x,2963487.15,0.1);
        $this->assertEqualsWithDelta($pointNAD27->y,255412.99,0.1 );

        $pointWGS84 = $proj4->transform($projWGS84,$pointNAD27);
        $this->assertEqualsWithDelta($pointWGS84->x,-96,0.1);
        $this->assertEqualsWithDelta($pointWGS84->y,28.5,0.1);

        $pointSrc = new Point(671196.3657,1230275.0454,$projOSGB36);
        $pointDest = $proj4->transform($projWGS84, $pointSrc);
        $this->assertEqualsWithDelta(2.9964931538756, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(60.863435314163, $pointDest->y, 0.1);

        $pointSrc = $pointDest;
        $pointDest = $proj4->transform($projOSGB36, $pointSrc);
        $this->assertEqualsWithDelta(671196.3657, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(1230275.0454, $pointDest->y, 0.1);

        //from @coreation
        $pointLCC2SP=new Point(78367.044643634, 166486.56503096, $projLCC2SP);
        $pointWGS84=new Point(3.3500208637038, 50.803896326566, $projWGS84);

        //Proj4php::setDebug(true);
        $pointWGS84Actual =$proj4->transform($projWGS84, $pointLCC2SP);
        $this->assertEqualsWithDelta($pointWGS84->x, $pointWGS84Actual->x, 0.1);
        $this->assertEqualsWithDelta($pointWGS84->y, $pointWGS84Actual->y, 0.1);
        //Proj4php::setDebug(false);

        $pointWGS84=new Point(3.3500208637038, 50.803896326566, $projWGS84);
        $pointLCC2SP=new Point(78367.044643634, 166486.56503096, $projLCC2SP);

        //Proj4php::setDebug(true);
        $pointLCC2SPActual=$proj4->transform($projLCC2SP, $pointWGS84);
        $this->assertEqualsWithDelta($pointLCC2SP->x, $pointLCC2SPActual->x, 0.1);
        $this->assertEqualsWithDelta($pointLCC2SP->y, $pointLCC2SPActual->y, 0.1);
        //Proj4php::setDebug(false);

        // from spatialreference.org (EPSG:31370 page)
        $pointLCC2SP=new Point(157361.845373, 132751.380618, $projLCC2SP);
        $pointWGS84=new Point(4.47, 50.505, $projWGS84);

        //Proj4php::setDebug(true);
        $pointWGS84Actual =$proj4->transform($projWGS84, $pointLCC2SP);
        $this->assertEqualsWithDelta($pointWGS84->x, $pointWGS84Actual->x, 0.0001);
        $this->assertEqualsWithDelta($pointWGS84->y, $pointWGS84Actual->y, 0.0001);
        //Proj4php::setDebug(false);

        $pointWGS84=new Point(4.47, 50.505, $projWGS84);
        $pointLCC2SP=new Point(157361.845373, 132751.380618, $projLCC2SP);

        //Proj4php::setDebug(true);
        $pointLCC2SPActual=$proj4->transform($projLCC2SP, $pointWGS84);
        $this->assertEqualsWithDelta($pointLCC2SP->x, $pointLCC2SPActual->x, 0.1);
        $this->assertEqualsWithDelta($pointLCC2SP->y, $pointLCC2SPActual->y, 0.1);
        //Proj4php::setDebug(false);

    }

    public function testInlineProjectionMethodWithSpace()
    {
        Proj4php::setDebug(false);

        $proj4           = new Proj4php();
        $projWGS84       = new Proj('EPSG:4326', $proj4);

        $projETRS89      = new Proj('PROJCS["ETRS89_UTM_zone_32N", GEOGCS["GCS_ETRS89", DATUM["D_ETRS_1989", SPHEROID["GRS_1980", 6378137.0, 298.257222101]], PRIMEM["Greenwich", 0.0], UNIT["degree", 0.017453292519943295], AXIS["Longitude", EAST], AXIS["Latitude", NORTH]], PROJECTION["Transverse_Mercator"], PARAMETER["central_meridian", 9.0], PARAMETER["latitude_of_origin", 0.0], PARAMETER["scale_factor", 0.9996], PARAMETER["false_easting", 500000.0], PARAMETER["false_northing", 0.0], UNIT["m", 1.0], AXIS["x", EAST], AXIS["y", NORTH]]',$proj4);

        $pointWGS84 = new Point(-96,28.5,  $projWGS84);
        $pointETRS89 = $proj4->transform($projETRS89,$pointWGS84);

        // TODO assert expected values here
        $this->assertTrue(true);
        
    }

    public function testInlineProjectionMethod2()
    {
        Proj4php::setDebug(false);

        $proj4           = new Proj4php();
        $projWGS84       = new Proj('EPSG:4326', $proj4);

        $projED50  = new Proj('GEOGCS["ED50",DATUM["European_Datum_1950",SPHEROID["International 1924",6378388,297,AUTHORITY["EPSG","7022"]],AUTHORITY["EPSG","6230"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4230"]]',$proj4);
        $projNAD27 = new Proj('PROJCS["NAD27 / Texas South Central",GEOGCS["NAD27",DATUM["North_American_Datum_1927",SPHEROID["Clarke 1866",6378206.4,294.9786982138982,AUTHORITY["EPSG","7008"]],AUTHORITY["EPSG","6267"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4267"]],UNIT["US survey foot",0.3048006096012192,AUTHORITY["EPSG","9003"]],PROJECTION["Lambert_Conformal_Conic_2SP"],PARAMETER["standard_parallel_1",28.38333333333333],PARAMETER["standard_parallel_2",30.28333333333333],PARAMETER["latitude_of_origin",27.83333333333333],PARAMETER["central_meridian",-99],PARAMETER["false_easting",2000000],PARAMETER["false_northing",0],AUTHORITY["EPSG","32040"],AXIS["X",EAST],AXIS["Y",NORTH]]',$proj4);
        $projLCC2SP = new Proj('PROJCS["Belge 1972 / Belgian Lambert 72",GEOGCS["Belge 1972",DATUM["Reseau_National_Belge_1972",SPHEROID["International 1924",6378388,297,AUTHORITY["EPSG","7022"]],TOWGS84[106.869,-52.2978,103.724,-0.33657,0.456955,-1.84218,1],AUTHORITY["EPSG","6313"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4313"]],UNIT["metre",1,AUTHORITY["EPSG","9001"]],PROJECTION["Lambert_Conformal_Conic_2SP"],PARAMETER["standard_parallel_1",51.16666723333333],PARAMETER["standard_parallel_2",49.8333339],PARAMETER["latitude_of_origin",90],PARAMETER["central_meridian",4.367486666666666],PARAMETER["false_easting",150000.013],PARAMETER["false_northing",5400088.438],AUTHORITY["EPSG","31370"],AXIS["X",EAST],AXIS["Y",NORTH]]',$proj4);
        $projITRF2000 = new Proj('GEODCRS["ITRF2000",DATUM["International Terrestrial Reference Frame 2000",ELLIPSOID["GRS 1980",6378137,298.257222101,LENGTHUNIT["metre",1.0]]],CS[ellipsoidal,2],AXIS["latitude",north,ORDER[1]],AXIS["longitude",east,ORDER[2]],ANGLEUNIT["degree",0.01745329252],ID["EPSG",8997]]',$proj4);

        // Proj4php::setDebug(true);

        $pointWGS84 = new Point(-96,28.5,  $projWGS84);
        $pointNAD27 = $proj4->transform($projNAD27,$pointWGS84);

        $this->assertEqualsWithDelta($pointNAD27->x,2963487.15,0.1);
        $this->assertEqualsWithDelta($pointNAD27->y,255412.99,0.1);

        // Proj4php::setDebug(false);

        $pointWGS84 = $proj4->transform($projWGS84,$pointNAD27);
        $this->assertEqualsWithDelta($pointWGS84->x,-96,0.1);
        $this->assertEqualsWithDelta($pointWGS84->y,28.5,0.1);

        //from @coreation
        $pointLCC2SP=new Point(78367.044643634, 166486.56503096, $projLCC2SP);

        // from http://cs2cs.mygeodata.eu/
        // using:
        // input projection: Belge 1972 / Belgian Lambert 72 (SRID=31370)
        // +proj=lcc +lat_1=51.16666723333333 +lat_2=49.8333339 +lat_0=90 +lon_0=4.367486666666666 
        // +x_0=150000.013 +y_0=5400088.438 +ellps=intl +towgs84=-106.868628,52.297783,-103.723893,0.336570,-0.456955,1.842183,-1.2747 +units=m +no_defs 
        // 
        // output projection:
        // WWGS 84 (SRID=4326)
        // +proj=longlat +datum=WGS84 +no_defs 
        $pointWGS84=new Point(3.3500208637038, 50.803896326566, $projWGS84);

        $pointWGS84Actual =$proj4->transform($projWGS84, $pointLCC2SP);
        $this->assertEqualsWithDelta($pointWGS84->x, $pointWGS84Actual->x, 0.1);
        $this->assertEqualsWithDelta($pointWGS84->y, $pointWGS84Actual->y, 0.1);

        // reverse transform.
        // I have to redefine the input/output expected points because above they 
        // are altered. (is that really the desired behavior?)
        $pointWGS84=new Point(3.3500208637038, 50.803896326566, $projWGS84);
        $pointLCC2SP=new Point(78367.044643634, 166486.56503096, $projLCC2SP);

        $pointLCC2SPActual=$proj4->transform($projLCC2SP, $pointWGS84);
        $this->assertEqualsWithDelta($pointLCC2SP->x, $pointLCC2SPActual->x, 0.1);
        $this->assertEqualsWithDelta($pointLCC2SP->y, $pointLCC2SPActual->y, 0.1);
    }

    public function testDatum()
    {
        Proj4php::setDebug(false);

        $proj4           = new Proj4php();
        $projWGS84       = new Proj('EPSG:4326', $proj4);

        $projED50  = new Proj('GEOGCS["ED50",DATUM["European_Datum_1950",SPHEROID["International 1924",6378388,297,AUTHORITY["EPSG","7022"]],AUTHORITY["EPSG","6230"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4230"]]',$proj4);

        // from http://www.ihsenergy.com/epsg/guid7.pdf
        // Chapter 2.3.2
        // 53°48'33.82"N
        // 2°07'46.38"E
        $pointWGS84 = new Point(deg2rad(53.809189444),deg2rad(2.129455), $projWGS84);

        $proj4->datum_transform($projWGS84->datum,$projED50->datum,$pointWGS84);

        $this->assertEqualsWithDelta(deg2rad(53.809189444),$pointWGS84->x,0.1);
        $this->assertEqualsWithDelta(deg2rad(2.129455),$pointWGS84->y,0.1);
    }

    public function testProjFour()
    {
        Proj4php::setDebug(false);

        $proj4           = new Proj4php();
        $projL93         = new Proj('EPSG:2154', $proj4);
        $projWGS84       = new Proj('EPSG:4326', $proj4);
        $projLI          = new Proj('EPSG:27571', $proj4);
        $projLSud        = new Proj('EPSG:27563', $proj4);
        $projLSeventyTwo = new Proj('EPSG:31370', $proj4);
        $projGDA94       = new Proj('EPSG:3112', $proj4);

        $pointSrc = new Point('652709.401', '6859290.946');
        $this->assertEquals('652709.401 6859290.946', $pointSrc->toShortString());

        $pointDest = $proj4->transform($projL93, $projWGS84, $pointSrc);
        $this->assertEqualsWithDelta(2.3557811127971, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(48.831938054369, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projWGS84, $projLSeventyTwo, $pointSrc);
        $this->assertEqualsWithDelta(2179.4161950587, $pointDest->x, 20);
        $this->assertEqualsWithDelta(-51404.55306690, $pointDest->y, 20);
        $this->assertEqualsWithDelta(2354.4969810662, $pointDest->x, 300);
        $this->assertEqualsWithDelta(-51359.251012595, $pointDest->y, 300);

        $pointDest = $proj4->transform($projLSeventyTwo, $projWGS84, $pointSrc);
        $this->assertEqualsWithDelta(2.3557811002407, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(48.831938050542, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(2.3557811127971, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(48.831938054369, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projWGS84, $projLSud, $pointSrc);
        $this->assertEqualsWithDelta(601419.93654252, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(726554.08650133, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(601419.93647681, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(726554.08650133, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projLSud, $projWGS84, $pointSrc);
        $this->assertEqualsWithDelta(2.3557810993491, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(48.831938051718, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(2.3557811002407, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(48.831938050527, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projWGS84, $projLI, $pointSrc);
        $this->assertEqualsWithDelta(601415.06988072, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(1125718.0309796, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(601415.06994621, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(1125718.0308472, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projLI, $projL93, $pointSrc);
        $this->assertEqualsWithDelta(652709.40007563, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(6859290.9456811, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(652709.40001126, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(6859290.9458141, $pointDest->y, 0.1);

        $pointDest = $proj4->transform($projGDA94, $projL93, $pointSrc);
        $this->assertEqualsWithDelta(7172106.7349943, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(13534125.230361, $pointDest->y, 0.1);
        $this->assertEqualsWithDelta(7172106.7349943, $pointDest->x, 0.1);
        $this->assertEqualsWithDelta(13534125.230361, $pointDest->y, 0.1);
    }

    public function testMonteMarioItaly()
    {
        $proj4 = new Proj4php();

        $projTO = new Proj('+proj=tmerc +lat_0=0 +lon_0=9 +k=0.9996 +x_0=1500000 +y_0=0 +ellps=intl +towgs84=-104.1, -49.1, -9.9, 0.971, -2.917, 0.714, -11.68 +units=m +no_defs', $proj4);
        //$this->fail(print_r($projTO, true));

        $projFROM = new Proj('GOOGLE', $proj4);

        $pointMin = new Point(1013714.5417662, 5692462.5159013);
        $pointMinTr = $proj4->transform($projFROM, $projTO, $pointMin);

        $this->assertEqualsWithDelta(array(1508344.3777571, 5032839.2985009), array($pointMinTr->x, $pointMinTr->y), 0.0001);
    }



    public function testMercatorAuxiliarySphere()
    {
        $proj4 = new Proj4php();

        $projFROM = new Proj('PROJCS["WGS_1984_Web_Mercator_Auxiliary_Sphere", GEOGCS["GCS_WGS_1984", DATUM["D_WGS_1984", SPHEROID["WGS_1984", 6378137, 298.257223563]], PRIMEM["Greenwich", 0], UNIT["Degree", 0.0174532925199433]], PROJECTION["Mercator_Auxiliary_Sphere"], PARAMETER["False_Easting", 0], PARAMETER["False_Northing", 0], PARAMETER["Central_Meridian", 0], PARAMETER["Standard_Parallel_1", 0], PARAMETER["Auxiliary_Sphere_Type", 0], UNIT["Meter", 1]]', $proj4);
        //$this->fail(print_r($projTO, true));
        $projTO = new Proj('GOOGLE', $proj4);
        $pointSource = new Point(16063634.897567, -4598958.4183454);
        $pointDest = $proj4->transform($projFROM, $projTO, $pointSource);

        $this->assertEqualsWithDelta(array($pointDest->x, $pointDest->y), array($pointDest->x, $pointDest->y), 0.0001);
    }
}
