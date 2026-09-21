<?php

use Vanderbilt\REDCap\Classes\DTOs\REDCapConfigDTO;
use Vanderbilt\REDCap\Classes\Fhir\FhirData;
use Vanderbilt\REDCap\Classes\Fhir\FhirClient;
use Vanderbilt\REDCap\Classes\Traits\SubjectTrait;
use Vanderbilt\REDCap\Classes\Fhir\FhirVersionManager;
use Vanderbilt\REDCap\Classes\Fhir\FhirSystem\FhirSystem;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMapping;
use Vanderbilt\REDCap\Classes\Fhir\Facades\FhirClientFacade;
use Vanderbilt\REDCap\Classes\Fhir\FhirMapping\FhirMappingGroup;
use Vanderbilt\REDCap\Classes\Fhir\FhirStats\FhirStatsCollector;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\RecordAdapter;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\FhirMetadataSource;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\AdjudicationManager;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Services\DataCacheService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Services\DataFetchService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DatabaseService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects\TemporalMapping;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCdpDecorator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\PreSelectionService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\ValueObjects\FieldInfoCollection;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DataRetrievalService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\ErrorHandlingService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\UserInterfaceService;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataEmailDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataVandyDecorator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DataProcessingService;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCustomDecorator;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DataOrganizationService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\DataNormalizationService;
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\Adjudication\AdjudicationTrackingService;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataAdverseEventDecorator;
use Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\FhirMetadataCapabilitiesDecorator;

/**
 * DynamicDataPull
 * This class is used for setup and execution of the real-time web service for extracting
 * data from external systems and importing into REDCap.
 */
class DynamicDataPull
{
	use SubjectTrait;

	/** tags for notifications */
	const NOTIFICATION_DATA_COLLECTED_FOR_SAVING = 'DynamicDataPull:dataCollectedForSaving';
	const NOTIFICATION_DATA_SAVED = 'DynamicDataPull:dataSaved';
	const NOTIFICATION_DATA_SAVED_FOR_ALL_EVENTS = 'DynamicDataPull:dataSavedForAllEvents';

	// Encryption salt for DDP source values
	const DDP_ENCRYPTION_KEY = "ds9p2PGh#hK4aV@GVH-YbPrtpWp*7SpeBW+RTujYHj%q35aOrQO/aCSVIFMKifl!S6Ql~JV";

	// Cron's record fetch limit per batch
	const FETCH_LIMIT_PER_BATCH = 20;

	// Cron's record limit per query to the log_event when checking the last time a record was modified
	const RECORD_LIMIT_PER_LOG_QUERY = 20;

	// Set min/max values for range for Day Offset and Default Day Offset
	const DAY_OFFSET_MIN = 0.01;
	const DAY_OFFSET_MAX = 365;

	const WEBSERVICE_TYPE_FHIR = 'FHIR';
	const WEBSERVICE_TYPE_CUSTOM = 'CUSTOM';

	// Current project_id for this object
	public $project_id = null;
	
	// Store the DDP type (FHIR or CUSTOM)
	public $realtime_webservice_type = null;

	// Variable to store this project's field mappings as an array
	public $field_mappings = null;
	
	// Store FHIR service endpoint information (full URL, params) in array
	private $fhirEndPointsParams = array();
	
	public $nonTemporalMultipleValueFields = array();
	
	
	// Field choice mapping for multiple choice demography fields	
	public static $demography_mc_mapping = array(	
		'gender'=>"F, Female | M, Male | UNK, Unknown", 
		'ethnicity'=>"2135-2, Hispanic or Latino | 2186-5, Not Hispanic or Latino | 2137-8, Spaniard | 2148-5, Mexican | 2155-0, Central American | 2165-9, South American | 2178-2, Latin American | 2180-8, Puerto Rican | 2182-4, Cuban | 2184-0, Dominican | 2138-6, Andalusian | 2139-4, Asturian | 2140-2, Castillian | 2141-0, Catalonian | 2142-8, Belearic Islander | 2143-6, Gallego | 2144-4, Valencian | 2145-1, Canarian | 2146-9, Spanish Basque | 2149-3, Mexican American | 2150-1, Mexicano | 2151-9, Chicano | 2152-7, La Raza | 2153-5, Mexican American Indian | 2156-8, Costa Rican | 2157-6, Guatemalan | 2158-4, Honduran | 2159-2, Nicaraguan | 2160-0, Panamanian | 2161-8, Salvadoran | 2162-6, Central American Indian | 2163-4, Canal Zone | 2166-7, Argentinean | 2167-5, Bolivian | 2168-3, Chilean | 2169-1, Colombian | 2170-9, Ecuadorian | 2171-7, Paraguayan | 2172-5, Peruvian | 2173-3, Uruguayan | 2174-1, Venezuelan | 2175-8, South American Indian | 2176-6, Criollo | UNK, Unknown", 
		'race'=>"1002-5, American Indian/Alaska Native | 2028-9, Asian | 2076-8, Native Hawaiian or Other Pacific Islander | 2054-5, Black or African American | 2106-3, White | 1004-1, American Indian | 1735-0, Alaska Native | 1006-6, Abenaki | 1008-2, Algonquian | 1010-8, Apache | 1021-5, Arapaho | 1026-4, Arikara | 1028-0, Assiniboine | 1030-6, Assiniboine Sioux | 1033-0, Bannock | 1035-5, Blackfeet | 1037-1, Brotherton | 1039-7, Burt Lake Band | 1041-3, Caddo | 1044-7, Cahuilla | 1053-8, California Tribes | 1068-6, Canadian and Latin American Indian | 1076-9, Catawba | 1078-5, Cayuse | 1080-1, Chehalis | 1082-7, Chemakuan | 1086-8, Chemehuevi | 1088-4, Cherokee | 1100-7, Cherokee Shawnee | 1102-3, Cheyenne | 1106-4, Cheyenne-Arapaho | 1108-0, Chickahominy | 1112-2, Chickasaw | 1114-8, Chinook | 1123-9, Chippewa | 1150-2, Chippewa Cree | 1153-6, Chitimacha | 1155-1, Choctaw | 1162-7, Chumash | 1165-0, Clear Lake | 1167-6, Coeur D'Alene | 1169-2, Coharie | 1171-8, Colorado River | 1173-4, Colville | 1175-9, Comanche | 1178-3, Coos, Lower Umpqua, Siuslaw | 1180-9, Coos | 1182-5, Coquilles | 1184-1, Costanoan | 1186-6, Coushatta | 1189-0, Cowlitz | 1191-6, Cree | 1193-2, Creek | 1207-0, Croatan | 1209-6, Crow | 1211-2, Cupeno | 1214-6, Delaware | 1222-9, Diegueno | 1233-6, Eastern Tribes | 1250-0, Esselen | 1252-6, Fort Belknap | 1254-2, Fort Berthold | 1256-7, Fort Mcdowell | 1258-3, Fort Hall | 1260-9, Gabrieleno | 1262-5, Grand Ronde | 1264-1, Gros Ventres | 1267-4, Haliwa | 1269-0, Hidatsa | 1271-6, Hoopa | 1275-7, Hoopa Extension | 1277-3, Houma | 1279-9, Inaja-Cosmit | 1281-5, Iowa | 1285-6, Iroquois | 1297-1, Juaneno | 1299-7, Kalispel | 1301-1, Karuk | 1303-7, Kaw | 1305-2, Kickapoo | 1309-4, Kiowa | 1312-8, Klallam | 1317-7, Klamath | 1319-3, Konkow | 1321-9, Kootenai | 1323-5, Lassik | 1325-0, Long Island | 1331-8, Luiseno | 1340-9, Lumbee | 1342-5, Lummi | 1344-1, Maidu | 1348-2, Makah | 1350-8, Maliseet | 1352-4, Mandan | 1354-0, Mattaponi | 1356-5, Menominee | 1358-1, Miami | 1363-1, Miccosukee | 1365-6, Micmac | 1368-0, Mission Indians | 1370-6, Miwok | 1372-2, Modoc | 1374-8, Mohegan | 1376-3, Mono | 1378-9, Nanticoke | 1380-5, Narragansett | 1382-1, Navajo | 1387-0, Nez Perce | 1389-6, Nomalaki | 1391-2, Northwest Tribes | 1403-5, Omaha | 1405-0, Oregon Athabaskan | 1407-6, Osage | 1409-2, Otoe-Missouria | 1411-8, Ottawa | 1416-7, Paiute | 1439-9, Pamunkey | 1441-5, Passamaquoddy | 1445-6, Pawnee | 1448-0, Penobscot | 1450-6, Peoria | 1453-0, Pequot | 1456-3, Pima | 1460-5, Piscataway | 1462-1, Pit River | 1464-7, Pomo | 1474-6, Ponca | 1478-7, Potawatomi | 1487-8, Powhatan | 1489-4, Pueblo | 1518-0, Puget Sound Salish | 1541-2, Quapaw | 1543-8, Quinault | 1545-3, Rappahannock | 1547-9, Reno-Sparks | 1549-5, Round Valley | 1551-1, Sac and Fox | 1556-0, Salinan | 1558-6, Salish | 1560-2, Salish and Kootenai | 1562-8, Schaghticoke | 1564-4, Scott Valley | 1566-9, Seminole | 1573-5, Serrano | 1576-8, Shasta | 1578-4, Shawnee | 1582-6, Shinnecock | 1584-2, Shoalwater Bay | 1586-7, Shoshone | 1602-2, Shoshone Paiute | 1607-1, Siletz | 1609-7, Sioux | 1643-6, Siuslaw | 1645-1, Spokane | 1647-7, Stewart | 1649-3, Stockbridge | 1651-9, Susanville | 1653-5, Tohono O'Odham | 1659-2, Tolowa | 1661-8, Tonkawa | 1663-4, Tygh | 1665-9, Umatilla | 1667-5, Umpqua | 1670-9, Ute | 1675-8, Wailaki | 1677-4, Walla-Walla | 1679-0, Wampanoag | 1683-2, Warm Springs | 1685-7, Wascopum | 1687-3, Washoe | 1692-3, Wichita | 1694-9, Wind River | 1696-4, Winnebago | 1700-4, Winnemucca | 1702-0, Wintun | 1704-6, Wiyot | 1707-9, Yakama | 1709-5, Yakama Cowlitz | 1711-1, Yaqui | 1715-2, Yavapai Apache | 1717-8, Yokuts | 1722-8, Yuchi | 1724-4, Yuman | 1732-7, Yurok | 1011-6, Chiricahua | 1012-4, Fort Sill Apache | 1013-2, Jicarilla Apache | 1014-0, Lipan Apache | 1015-7, Mescalero Apache | 1016-5, Oklahoma Apache | 1017-3, Payson Apache | 1018-1, San Carlos Apache | 1019-9, White Mountain Apache | 1022-3, Northern Arapaho | 1023-1, Southern Arapaho | 1024-9, Wind River Arapaho | 1031-4, Fort Peck Assiniboine Sioux | 1042-1, Oklahoma Cado | 1045-4, Agua Caliente Cahuilla | 1046-2, Augustine | 1047-0, Cabazon | 1048-8, Los Coyotes | 1049-6, Morongo | 1050-4, Santa Rosa Cahuilla | 1051-2, Torres-Martinez | 1054-6, Cahto | 1055-3, Chimariko | 1056-1, Coast Miwok | 1057-9, Digger | 1058-7, Kawaiisu | 1059-5, Kern River | 1060-3, Mattole | 1061-1, Red Wood | 1062-9, Santa Rosa | 1063-7, Takelma | 1064-5, Wappo | 1065-2, Yana | 1066-0, Yuki | 1069-4, Canadian Indian | 1070-2, Central American Indian | 1071-0, French American Indian | 1072-8, Mexican American Indian | 1073-6, South American Indian | 1074-4, Spanish American Indian | 1083-5, Hoh | 1084-3, Quileute | 1089-2, Cherokee Alabama | 1090-0, Cherokees of Northeast Alabama | 1091-8, Cherokees of Southeast Alabama | 1092-6, Eastern Cherokee | 1093-4, Echota Cherokee | 1094-2, Etowah Cherokee | 1095-9, Northern Cherokee | 1096-7, Tuscola | 1097-5, United Keetowah Band of Cherokee | 1098-3, Western Cherokee | 1103-1, Northern Cheyenne | 1104-9, Southern Cheyenne | 1109-8, Eastern Chickahominy | 1110-6, Western Chickahominy | 1115-5, Clatsop | 1116-3, Columbia River Chinook | 1117-1, Kathlamet | 1118-9, Upper Chinook | 1119-7, Wakiakum Chinook | 1120-5, Willapa Chinook | 1121-3, Wishram | 1124-7, Bad River | 1125-4, Bay Mills Chippewa | 1126-2, Bois Forte | 1127-0, Burt Lake Chippewa | 1128-8, Fond du Lac | 1129-6, Grand Portage | 1130-4, Grand Traverse Band of Ottawa/Chippewa | 1131-2, Keweenaw | 1132-0, Lac Courte Oreilles | 1133-8, Lac du Flambeau | 1134-6, Lac Vieux Desert Chippewa | 1135-3, Lake Superior | 1136-1, Leech Lake | 1137-9, Little Shell Chippewa | 1138-7, Mille Lacs | 1139-5, Minnesota Chippewa | 1140-3, Ontonagon | 1141-1, Red Cliff Chippewa | 1142-9, Red Lake Chippewa | 1143-7, Saginaw Chippewa | 1144-5, St. Croix Chippewa | 1145-2, Sault Ste. Marie Chippewa | 1146-0, Sokoagon Chippewa | 1147-8, Turtle Mountain | 1148-6, White Earth | 1151-0, Rocky Boy's Chippewa Cree | 1156-9, Clifton Choctaw | 1157-7, Jena Choctaw | 1158-5, Mississippi Choctaw | 1159-3, Mowa Band of Choctaw | 1160-1, Oklahoma Choctaw | 1163-5, Santa Ynez | 1176-7, Oklahoma Comanche | 1187-4, Alabama Coushatta | 1194-0, Alabama Creek | 1195-7, Alabama Quassarte | 1196-5, Eastern Creek | 1197-3, Eastern Muscogee | 1198-1, Kialegee | 1199-9, Lower Muscogee | 1200-5, Machis Lower Creek Indian | 1201-3, Poarch Band | 1202-1, Principal Creek Indian Nation | 1203-9, Star Clan of Muscogee Creeks | 1204-7, Thlopthlocco | 1205-4, Tuckabachee | 1212-0, Agua Caliente | 1215-3, Eastern Delaware | 1216-1, Lenni-Lenape | 1217-9, Munsee | 1218-7, Oklahoma Delaware | 1219-5, Rampough Mountain | 1220-3, Sand Hill | 1223-7, Campo | 1224-5, Capitan Grande | 1225-2, Cuyapaipe | 1226-0, La Posta | 1227-8, Manzanita | 1228-6, Mesa Grande | 1229-4, San Pasqual | 1230-2, Santa Ysabel | 1231-0, Sycuan | 1234-4, Attacapa | 1235-1, Biloxi | 1236-9, Georgetown (Eastern Tribes) | 1237-7, Moor | 1238-5, Nansemond | 1239-3, Natchez | 1240-1, Nausu Waiwash | 1241-9, Nipmuc | 1242-7, Paugussett | 1243-5, Pocomoke Acohonock | 1244-3, Southeastern Indians | 1245-0, Susquehanock | 1246-8, Tunica Biloxi | 1247-6, Waccamaw-Siousan | 1248-4, Wicomico | 1265-8, Atsina | 1272-4, Trinity | 1273-2, Whilkut | 1282-3, Iowa of Kansas-Nebraska | 1283-1, Iowa of Oklahoma | 1286-4, Cayuga | 1287-2, Mohawk | 1288-0, Oneida | 1289-8, Onondaga | 1290-6, Seneca | 1291-4, Seneca Nation | 1292-2, Seneca-Cayuga | 1293-0, Tonawanda Seneca | 1294-8, Tuscarora | 1295-5, Wyandotte | 1306-0, Oklahoma Kickapoo | 1307-8, Texas Kickapoo | 1310-2, Oklahoma Kiowa | 1313-6, Jamestown | 1314-4, Lower Elwha | 1315-1, Port Gamble Klallam | 1326-8, Matinecock | 1327-6, Montauk | 1328-4, Poospatuck | 1329-2, Setauket | 1332-6, La Jolla | 1333-4, Pala | 1334-2, Pauma | 1335-9, Pechanga | 1336-7, Soboba | 1337-5, Twenty-Nine Palms | 1338-3, Temecula | 1345-8, Mountain Maidu | 1346-6, Nishinam | 1359-9, Illinois Miami | 1360-7, Indiana Miami | 1361-5, Oklahoma Miami | 1366-4, Aroostook | 1383-9, Alamo Navajo | 1384-7, Canoncito Navajo | 1385-4, Ramah Navajo | 1392-0, Alsea | 1393-8, Celilo | 1394-6, Columbia | 1395-3, Kalapuya | 1396-1, Molala | 1397-9, Talakamish | 1398-7, Tenino | 1399-5, Tillamook | 1400-1, Wenatchee | 1401-9, Yahooskin | 1412-6, Burt Lake Ottawa | 1413-4, Michigan Ottawa | 1414-2, Oklahoma Ottawa | 1417-5, Bishop | 1418-3, Bridgeport | 1419-1, Burns Paiute | 1420-9, Cedarville | 1421-7, Fort Bidwell | 1422-5, Fort Independence | 1423-3, Kaibab | 1424-1, Las Vegas | 1425-8, Lone Pine | 1426-6, Lovelock | 1427-4, Malheur Paiute | 1428-2, Moapa | 1429-0, Northern Paiute | 1430-8, Owens Valley | 1431-6, Pyramid Lake | 1432-4, San Juan Southern Paiute | 1433-2, Southern Paiute | 1434-0, Summit Lake | 1435-7, Utu Utu Gwaitu Paiute | 1436-5, Walker River | 1437-3, Yerington Paiute | 1442-3, Indian Township | 1443-1, Pleasant Point Passamaquoddy | 1446-4, Oklahoma Pawnee | 1451-4, Oklahoma Peoria | 1454-8, Marshantucket Pequot | 1457-1, Gila River Pima-Maricopa | 1458-9, Salt River Pima-Maricopa | 1465-4, Central Pomo | 1466-2, Dry Creek | 1467-0, Eastern Pomo | 1468-8, Kashia | 1469-6, Northern Pomo | 1470-4, Scotts Valley | 1471-2, Stonyford | 1472-0, Sulphur Bank | 1475-3, Nebraska Ponca | 1476-1, Oklahoma Ponca | 1479-5, Citizen Band Potawatomi | 1480-3, Forest County | 1481-1, Hannahville | 1482-9, Huron Potawatomi | 1483-7, Pokagon Potawatomi | 1484-5, Prairie Band | 1485-2, Wisconsin Potawatomi | 1490-2, Acoma | 1491-0, Arizona Tewa | 1492-8, Cochiti | 1493-6, Hopi | 1494-4, Isleta | 1495-1, Jemez | 1496-9, Keres | 1497-7, Laguna | 1498-5, Nambe | 1499-3, Picuris | 1500-8, Piro | 1501-6, Pojoaque | 1502-4, San Felipe | 1503-2, San Ildefonso | 1504-0, San Juan Pueblo | 1505-7, San Juan De | 1506-5, San Juan | 1507-3, Sandia | 1508-1, Santa Ana | 1509-9, Santa Clara | 1510-7, Santo Domingo | 1511-5, Taos | 1512-3, Tesuque | 1513-1, Tewa | 1514-9, Tigua | 1515-6, Zia | 1516-4, Zuni | 1519-8, Duwamish | 1520-6, Kikiallus | 1521-4, Lower Skagit | 1522-2, Muckleshoot | 1523-0, Nisqually | 1524-8, Nooksack | 1525-5, Port Madison | 1526-3, Puyallup | 1527-1, Samish | 1528-9, Sauk-Suiattle | 1529-7, Skokomish | 1530-5, Skykomish | 1531-3, Snohomish | 1532-1, Snoqualmie | 1533-9, Squaxin Island | 1534-7, Steilacoom | 1535-4, Stillaguamish | 1536-2, Suquamish | 1537-0, Swinomish | 1538-8, Tulalip | 1539-6, Upper Skagit | 1552-9, Iowa Sac and Fox | 1553-7, Missouri Sac and Fox | 1554-5, Oklahoma Sac and Fox | 1567-7, Big Cypress | 1568-5, Brighton | 1569-3, Florida Seminole | 1570-1, Hollywood Seminole | 1571-9, Oklahoma Seminole | 1574-3, San Manual | 1579-2, Absentee Shawnee | 1580-0, Eastern Shawnee | 1587-5, Battle Mountain | 1588-3, Duckwater | 1589-1, Elko | 1590-9, Ely | 1591-7, Goshute | 1592-5, Panamint | 1593-3, Ruby Valley | 1594-1, Skull Valley | 1595-8, South Fork Shoshone | 1596-6, Te-Moak Western Shoshone | 1597-4, Timbi-Sha Shoshone | 1598-2, Washakie | 1599-0, Wind River Shoshone | 1600-6, Yomba | 1603-0, Duck Valley | 1604-8, Fallon | 1605-5, Fort McDermitt | 1610-5, Blackfoot Sioux | 1611-3, Brule Sioux | 1612-1, Cheyenne River Sioux | 1613-9, Crow Creek Sioux | 1614-7, Dakota Sioux | 1615-4, Flandreau Santee | 1616-2, Fort Peck | 1617-0, Lake Traverse Sioux | 1618-8, Lower Brule Sioux | 1619-6, Lower Sioux | 1620-4, Mdewakanton Sioux | 1621-2, Miniconjou | 1622-0, Oglala Sioux | 1623-8, Pine Ridge Sioux | 1624-6, Pipestone Sioux | 1625-3, Prairie Island Sioux | 1626-1, Prior Lake Sioux | 1627-9, Rosebud Sioux | 1628-7, Sans Arc Sioux | 1629-5, Santee Sioux | 1630-3, Sisseton-Wahpeton | 1631-1, Sisseton Sioux | 1632-9, Spirit Lake Sioux | 1633-7, Standing Rock Sioux | 1634-5, Teton Sioux | 1635-2, Two Kettle Sioux | 1636-0, Upper Sioux | 1637-8, Wahpekute Sioux | 1638-6, Wahpeton Sioux | 1639-4, Wazhaza Sioux | 1640-2, Yankton Sioux | 1641-0, Yanktonai Sioux | 1654-3, Ak-Chin | 1655-0, Gila Bend | 1656-8, San Xavier | 1657-6, Sells | 1668-3, Cow Creek Umpqua | 1671-7, Allen Canyon | 1672-5, Uintah Ute | 1673-3, Ute Mountain Ute | 1680-8, Gay Head Wampanoag | 1681-6, Mashpee Wampanoag | 1688-1, Alpine | 1689-9, Carson | 1690-7, Dresslerville | 1697-2, Ho-chunk | 1698-0, Nebraska Winnebago | 1705-3, Table Bluff | 1712-9, Barrio Libre | 1713-7, Pascua Yaqui | 1718-6, Chukchansi | 1719-4, Tachi | 1720-2, Tule River | 1725-1, Cocopah | 1726-9, Havasupai | 1727-7, Hualapai | 1728-5, Maricopa | 1729-3, Mohave | 1730-1, Quechan | 1731-9, Yavapai | 1733-5, Coast Yurok | 1737-6, Alaska Indian | 1840-8, Eskimo | 1966-1, Aleut | 1739-2, Alaskan Athabascan | 1811-9, Southeast Alaska | 1740-0, Ahtna | 1741-8, Alatna | 1742-6, Alexander | 1743-4, Allakaket | 1744-2, Alanvik | 1745-9, Anvik | 1746-7, Arctic | 1747-5, Beaver | 1748-3, Birch Creek | 1749-1, Cantwell | 1750-9, Chalkyitsik | 1751-7, Chickaloon | 1752-5, Chistochina | 1753-3, Chitina | 1754-1, Circle | 1755-8, Cook Inlet | 1756-6, Copper Center | 1757-4, Copper River | 1758-2, Dot Lake | 1759-0, Doyon | 1760-8, Eagle | 1761-6, Eklutna | 1762-4, Evansville | 1763-2, Fort Yukon | 1764-0, Gakona | 1765-7, Galena | 1766-5, Grayling | 1767-3, Gulkana | 1768-1, Healy Lake | 1769-9, Holy Cross | 1770-7, Hughes | 1771-5, Huslia | 1772-3, Iliamna | 1773-1, Kaltag | 1774-9, Kluti Kaah | 1775-6, Knik | 1776-4, Koyukuk | 1777-2, Lake Minchumina | 1778-0, Lime | 1779-8, Mcgrath | 1780-6, Manley Hot Springs | 1781-4, Mentasta Lake | 1782-2, Minto | 1783-0, Nenana | 1784-8, Nikolai | 1785-5, Ninilchik | 1786-3, Nondalton | 1787-1, Northway | 1788-9, Nulato | 1789-7, Pedro Bay | 1790-5, Rampart | 1791-3, Ruby | 1792-1, Salamatof | 1793-9, Seldovia | 1794-7, Slana | 1795-4, Shageluk | 1796-2, Stevens | 1797-0, Stony River | 1798-8, Takotna | 1799-6, Tanacross | 1800-2, Tanaina | 1801-0, Tanana | 1802-8, Tanana Chiefs | 1803-6, Tazlina | 1804-4, Telida | 1805-1, Tetlin | 1806-9, Tok | 1807-7, Tyonek | 1808-5, Venetie | 1809-3, Wiseman | 1813-5, Tlingit-Haida | 1837-4, Tsimshian | 1814-3, Angoon | 1815-0, Central Council of Tlingit and Haida Tribes | 1816-8, Chilkat | 1817-6, Chilkoot | 1818-4, Craig | 1819-2, Douglas | 1820-0, Haida | 1821-8, Hoonah | 1822-6, Hydaburg | 1823-4, Kake | 1824-2, Kasaan | 1825-9, Kenaitze | 1826-7, Ketchikan | 1827-5, Klawock | 1828-3, Pelican | 1829-1, Petersburg | 1830-9, Saxman | 1831-7, Sitka | 1832-5, Tenakee Springs | 1833-3, Tlingit | 1834-1, Wrangell | 1835-8, Yakutat | 1838-2, Metlakatla | 1842-4, Greenland Eskimo | 1844-0, Inupiat Eskimo | 1891-1, Siberian Eskimo | 1896-0, Yupik Eskimo | 1845-7, Ambler | 1846-5, Anaktuvuk | 1847-3, Anaktuvuk Pass | 1848-1, Arctic Slope Inupiat | 1849-9, Arctic Slope Corporation | 1850-7, Atqasuk | 1851-5, Barrow | 1852-3, Bering Straits Inupiat | 1853-1, Brevig Mission | 1854-9, Buckland | 1855-6, Chinik | 1856-4, Council | 1857-2, Deering | 1858-0, Elim | 1859-8, Golovin | 1860-6, Inalik Diomede | 1861-4, Inupiaq | 1862-2, Kaktovik | 1863-0, Kawerak | 1864-8, Kiana | 1865-5, Kivalina | 1866-3, Kobuk | 1867-1, Kotzebue | 1868-9, Koyuk | 1869-7, Kwiguk | 1870-5, Mauneluk Inupiat | 1871-3, Nana Inupiat | 1872-1, Noatak | 1873-9, Nome | 1874-7, Noorvik | 1875-4, Nuiqsut | 1876-2, Point Hope | 1877-0, Point Lay | 1878-8, Selawik | 1879-6, Shaktoolik | 1880-4, Shishmaref | 1881-2, Shungnak | 1882-0, Solomon | 1883-8, Teller | 1884-6, Unalakleet | 1885-3, Wainwright | 1886-1, Wales | 1887-9, White Mountain | 1888-7, White Mountain Inupiat | 1889-5, Mary's Igloo | 1892-9, Gambell | 1893-7, Savoonga | 1894-5, Siberian Yupik | 1897-8, Akiachak | 1898-6, Akiak | 1899-4, Alakanuk | 1900-0, Aleknagik | 1901-8, Andreafsky | 1902-6, Aniak | 1903-4, Atmautluak | 1904-2, Bethel | 1905-9, Bill Moore's Slough | 1906-7, Bristol Bay Yupik | 1907-5, Calista Yupik | 1908-3, Chefornak | 1909-1, Chevak | 1910-9, Chuathbaluk | 1911-7, Clark's Point | 1912-5, Crooked Creek | 1913-3, Dillingham | 1914-1, Eek | 1915-8, Ekuk | 1916-6, Ekwok | 1917-4, Emmonak | 1918-2, Goodnews Bay | 1919-0, Hooper Bay | 1920-8, Iqurmuit (Russian Mission) | 1921-6, Kalskag | 1922-4, Kasigluk | 1923-2, Kipnuk | 1924-0, Koliganek | 1925-7, Kongiganak | 1926-5, Kotlik | 1927-3, Kwethluk | 1928-1, Kwigillingok | 1929-9, Levelock | 1930-7, Lower Kalskag | 1931-5, Manokotak | 1932-3, Marshall | 1933-1, Mekoryuk | 1934-9, Mountain Village | 1935-6, Naknek | 1936-4, Napaumute | 1937-2, Napakiak | 1938-0, Napaskiak | 1939-8, Newhalen | 1940-6, New Stuyahok | 1941-4, Newtok | 1942-2, Nightmute | 1943-0, Nunapitchukv | 1944-8, Oscarville | 1945-5, Pilot Station | 1946-3, Pitkas Point | 1947-1, Platinum | 1948-9, Portage Creek | 1949-7, Quinhagak | 1950-5, Red Devil | 1951-3, St. Michael | 1952-1, Scammon Bay | 1953-9, Sheldon's Point | 1954-7, Sleetmute | 1955-4, Stebbins | 1956-2, Togiak | 1957-0, Toksook | 1958-8, Tulukskak | 1959-6, Tuntutuliak | 1960-4, Tununak | 1961-2, Twin Hills | 1962-0, Georgetown (Yupik-Eskimo) | 1963-8, St. Mary's | 1964-6, Umkumiate | 1968-7, Alutiiq Aleut | 1972-9, Bristol Bay Aleut | 1984-4, Chugach Aleut | 1990-1, Eyak | 1992-7, Koniag Aleut | 2002-4, Sugpiaq | 2004-0, Suqpigaq | 2006-5, Unangan Aleut | 1969-5, Tatitlek | 1970-3, Ugashik | 1973-7, Chignik | 1974-5, Chignik Lake | 1975-2, Egegik | 1976-0, Igiugig | 1977-8, Ivanof Bay | 1978-6, King Salmon | 1979-4, Kokhanok | 1980-2, Perryville | 1981-0, Pilot Point | 1982-8, Port Heiden | 1985-1, Chenega | 1986-9, Chugach Corporation | 1987-7, English Bay | 1988-5, Port Graham | 1993-5, Akhiok | 1994-3, Agdaagux | 1995-0, Karluk | 1996-8, Kodiak | 1997-6, Larsen Bay | 1998-4, Old Harbor | 1999-2, Ouzinkie | 2000-8, Port Lions | 2007-3, Akutan | 2008-1, Aleut Corporation | 2009-9, Aleutian | 2010-7, Aleutian Islander | 2011-5, Atka | 2012-3, Belkofski | 2013-1, Chignik Lagoon | 2014-9, King Cove | 2015-6, False Pass | 2016-4, Nelson Lagoon | 2017-2, Nikolski | 2018-0, Pauloff Harbor | 2019-8, Qagan Toyagungin | 2020-6, Qawalangin | 2021-4, St. George | 2022-2, St. Paul | 2023-0, Sand Point | 2024-8, South Naknek | 2025-5, Unalaska | 2026-3, Unga | 2029-7, Asian Indian | 2030-5, Bangladeshi | 2031-3, Bhutanese | 2032-1, Burmese | 2033-9, Cambodian | 2034-7, Chinese | 2035-4, Taiwanese | 2036-2, Filipino | 2037-0, Hmong | 2038-8, Indonesian | 2039-6, Japanese | 2040-4, Korean | 2041-2, Laotian | 2042-0, Malaysian | 2043-8, Okinawan | 2044-6, Pakistani | 2045-3, Sri Lankan | 2046-1, Thai | 2047-9, Vietnamese | 2048-7, Iwo Jiman | 2049-5, Maldivian | 2050-3, Nepalese | 2051-1, Singaporean | 2052-9, Madagascar | 2056-0, Black | 2058-6, African American | 2060-2, African | 2067-7, Bahamian | 2068-5, Barbadian | 2069-3, Dominican | 2070-1, Dominica Islander | 2071-9, Haitian | 2072-7, Jamaican | 2073-5, Tobagoan | 2074-3, Trinidadian | 2075-0, West Indian | 2061-0, Botswanan | 2062-8, Ethiopian | 2063-6, Liberian | 2064-4, Namibian | 2065-1, Nigerian | 2066-9, Zairean | 2078-4, Polynesian | 2085-9, Micronesian | 2100-6, Melanesian | 2500-7, Other Pacific Islander | 2079-2, Native Hawaiian | 2080-0, Samoan | 2081-8, Tahitian | 2082-6, Tongan | 2083-4, Tokelauan | 2086-7, Guamanian or Chamorro | 2087-5, Guamanian | 2088-3, Chamorro | 2089-1, Mariana Islander | 2090-9, Marshallese | 2091-7, Palauan | 2092-5, Carolinian | 2093-3, Kosraean | 2094-1, Pohnpeian | 2095-8, Saipanese | 2096-6, Kiribati | 2097-4, Chuukese | 2098-2, Yapese | 2101-4, Fijian | 2102-2, Papua New Guinean | 2103-0, Solomon Islander | 2104-8, New Hebrides | 2108-9, European | 2118-8, Middle Eastern or North African | 2129-5, Arab | 2109-7, Armenian | 2110-5, English | 2111-3, French | 2112-1, German | 2113-9, Irish | 2114-7, Italian | 2115-4, Polish | 2116-2, Scottish | 2119-6, Assyrian | 2120-4, Egyptian | 2121-2, Iranian | 2122-0, Iraqi | 2123-8, Lebanese | 2124-6, Palestinian | 2125-3, Syrian | 2126-1, Afghanistani | 2127-9, Israeili | 2131-1, Other Race | UNK, Unknown",
		'sex-for-clinical-use'=>"female, Female | male, Male", 
		'legal-sex'=>"female, Female | male, Male", 
	);

	/**
	 * fhir data container
	 * contains data and errors collected with FHIR
	 *
	 * @var FhirData
	 */
	public $fhirData = null;

	/**
	 *
	 * @var FhirSystem
	 */
	public $fhirSystem;

	/**
	 * CONSTRUCTOR
	 */
	public function __construct($this_project_id=null, $realtime_webservice_type=self::WEBSERVICE_TYPE_CUSTOM)
	{
		// Set project_id for this object
		if ($this_project_id === 0) {
			$this->project_id = $this_project_id;
		} elseif ($this_project_id === null) {
			if (defined("PROJECT_ID")) {
				$this->project_id = 0;
			} else {
				throw new Exception('No project_id provided!');
			}
		} else {
			$this->project_id = $this_project_id;
		}
		// Set the DDP type
		$this->realtime_webservice_type = $realtime_webservice_type;
		
		// set the FHIR system if in FHIR context
		if($realtime_webservice_type === self::WEBSERVICE_TYPE_FHIR) {
			$this->fhirSystem = FhirSystem::fromProjectId($this->project_id);
		}
	}

	public static function forProject($project_id) {
		$project = new Project($project_id);
		$realtime_webservice_enabled = boolval($project->project['realtime_webservice_enabled'] ?? false);
		$realtime_webservice_type = $project->project['realtime_webservice_type'] ?? false;
		if(!$realtime_webservice_enabled) return;
		if(!in_array($realtime_webservice_type, [self::WEBSERVICE_TYPE_FHIR, self::WEBSERVICE_TYPE_CUSTOM])) return;
		return new self($project_id, $realtime_webservice_type);
	}


	/**
	 * CALL "DATA" WEB SERVICE AND DISPLAY IN TABLE FOR ADJUDICATION
	 */
	public function fetchAndOutputData($record=null, $event_id=null, $form_data=array(), $day_offset=0, $day_offset_plusminus='+-',
									   $output_html=true, $record_exists='1', $show_excluded=false, $forceDataFetch=true, $instance=1, $repeat_instrument="")
	{
		global $Proj, $lang, $isAjax;
		// Validate $day_offset. If not valid, set to 0.
		if (!is_numeric($day_offset)) $day_offset = 0;
		if ($day_offset_plusminus != '-' && $day_offset_plusminus != '+') $day_offset_plusminus = '+-';
		// Get the REDCap field name and event_id of the external identifier
		list ($rc_field, $rc_event) = $this->getMappedIdRedcapFieldEvent();
		// ensure PROJECT_ID is defined
		if(!defined('PROJECT_ID')) define('PROJECT_ID', $Proj->project_id);
		// Obtain the value of the record identifier (e.g., mrn)
		$rc_data = Records::getData($Proj->project_id, 'array', $record, $rc_field, $rc_event);
		// Reset some values if we're not on a repeating instrument
		if (!($instance > 0 && is_numeric($event_id) && ($Proj->isRepeatingEvent($event_id) || $Proj->isRepeatingForm($event_id, $repeat_instrument)))) {
			$repeat_instrument = "";
			$instance = 0;
		} elseif ($Proj->isRepeatingEvent($event_id)) {
			$repeat_instrument = "";
		}
		// If form values were sent (which might be non-saved values), add them on top of $rc_data
		if (!empty($form_data) && is_numeric($event_id)) {
			// Loop through vars and remove all non-real fields
			foreach ($form_data as $key=>$val)
			{
				// If begin with double underscore (this ignores checkboxes, which we can't use for the RTWS)
				if (substr($key, 0, 2) == '__') { unset($form_data[$key]); continue; }
				// If end with ___radio
				if (substr($key, -8) == '___radio') { unset($form_data[$key]); continue; }
				// If contains a hyphen
				if (strpos($key, '-') !== false) { unset($form_data[$key]); continue; }
				// If is a reserved field name
				if (isset(Project::$reserved_field_names[$key])) { unset($form_data[$key]); continue; }

				// If a date[time] field that's not in YMD format, then convert to YMD
				// ONLY do this if being called via AJAX (i.e. form post from data entry form, where dates may not be in YMD format)
				if ($isAjax) {
					$thisValType = $Proj->metadata[$key]['element_validation_type'] ?? '';
					if (substr($thisValType, 0, 4) == 'date' && (substr($thisValType, -4) == '_mdy'|| substr($thisValType, -4) == '_dmy')) {
						$form_data[$key] = $val = DateTimeRC::datetimeConvert($val, substr($thisValType, -3), 'ymd');
					}
				}

				// If this is the REDCap field mapped to the external id field, then overwrite it's value
				if ($key == $rc_field && $event_id == $rc_event) {
					// Overwrite value
					$rc_data[$record][$rc_event][$rc_field] = $val;
				}
			}
		}
		// Get the value of the external id field (e.g., the MRN value)
		$record_identifier_external = $rc_data[$record][$rc_event][$rc_field] ?? '';
		// If doesn't have a record identifer external for this record, give error message
		if ($record_identifier_external == '') {
			// Go ahead and add timestamp for updated_at for record so that the cron doesn't keep calling it
			$sql = "update redcap_ddp_records set updated_at = '".NOW."', fetch_status = null
					where project_id = ".$this->project_id." and record = '".db_escape($record)."'";
			db_query($sql);
			// Return error message
			return 	array(0, RCView::div(array('class'=>"red", 'style'=>'margin:20px 0;max-width:100%;padding:10px;'),
								RCView::img(array('src'=>'exclamation.png')) .
								RCView::b($lang['global_01'].$lang['colon'])." ".
								"{$lang['global_49']} <b>$record</b> {$lang['ws_135']} \"$rc_field\"{$lang['period']} {$lang['ws_136']}"
							) .
							// Set hidden div so that jQuery knows to hide the dialog buttons
							RCView::div(array('id'=>'adjud_hide_buttons', 'class'=>'hidden'), '1')
					);
		}

		// Get data via web service and return as array
		list ($response_data_array, $request_field_array) = $this->fetchData(
			$record,
			$event_id,
			$record_identifier_external,
			$form_data,
			$forceDataFetch,
			$record_exists,
			$instance,
			$repeat_instrument
		);

		$output = [0=> 0, 1 =>''];
		
		// Create adjudication manager and process data
		$adjudicationManager = $this->makeAdjudicationManager();
		
		// Process the data with exception handling
		try {
			$adjudicationManager->processData($record, $event_id, [
				'data_array_src' => $response_data_array,
				'form_data' => $form_data,
				'instance' => $instance,
				'repeat_instrument' => $repeat_instrument,
				'field_mappings' => $request_field_array,
				'day_offset' => $day_offset,
				'day_offset_plusminus' => $day_offset_plusminus,
				'last_fetch_time' => $this->getLastFetchTime($record, true),
				'page' => $_GET['page'] ?? null,
			]);
			
			$output = [ 0 => $adjudicationManager->getItemCount() ];
			
			// Generate HTML if needed (only if processing succeeded)
			if ($output_html) {
				$html = $adjudicationManager->generateHtml();
				$wrapper = "<div class=\"mt-2\">$html</div>";
				$output[1] = $wrapper;
			}
		} catch (\Throwable $th) {
			// Handle processing errors
			$output = [
				0 => 0, // Set item count to 0 on failure
			];
			
			if ($output_html) {
				$message = $th->getMessage();
				$code = $th->getCode();
				$errorHtml = "<div class=\"alert alert-danger\">Error processing adjudication data: $message – code $code</div>";
				$wrapper = "<div class=\"mt-2\">$errorHtml</div>";
				$output[1] = $wrapper;
			}
		}
		
		return $output;
	}


	/**
	 * CALL "DATA" WEB SERVICE TO OBTAIN DATA FROM RECORD PASSED TO IT
	 * Return data as array with unique field name as array keys and data values as array values.
	 * If not JSON encoded, then return FALSE.
	 */
	// public function fetchData($record_identifier_rc, $event_id, $record_identifier_external, $day_offset,
	// 						  $day_offset_plusminus, $form_data=array(), $forceDataFetch=true, $record_exists='1', $returnCachedValues=true,
	// 						  $project_id=null, $instance=0, $repeat_instrument="")

	public function fetchData( $record, $event_id, $record_identifier_external, $form_data, $forceDataFetch, $record_exists, $instance, $repeat_instrument, $proj = null) {
		if(!$proj) $proj = new Project($this->project_id);
		$day_offset = $proj->project['realtime_webservice_offset_days'];
		$day_offset_plusminus = $proj->project['realtime_webservice_offset_plusminus'];

		$username = defined('USERID') ? USERID : null;
		$user_id = User::getUIIDByUsername($username);
		$dataCacheService = new DataCacheService($this->project_id);
		$dataFetchService = new DataFetchService($this, $this->project_id, $user_id);

		$previousCache = [];
		$mappings = $this->getMappedFields();
		$forceDataFetch = $dataFetchService->shouldForceDataFetch($record, $forceDataFetch, $record_exists);
		if($forceDataFetch || !$record_exists) {
			$newlyFetchedData = $dataFetchService->fetchData(
				$record,
				$event_id,
				$record_identifier_external,
				$day_offset,
				$day_offset_plusminus,
				$form_data,
				$instance,
				$repeat_instrument
			);

			if($record_exists) {
				$previousCache = $dataCacheService->getCachedData($record, $mappings);
				$convert_timestamp_from_gmt = $dataFetchService->shouldConvertTimestampFromGMT();

				$temporalMapping = TemporalMapping::fromMappedFields($mappings);
				$dataCacheService->cacheData($record, $newlyFetchedData, $mappings, $temporalMapping->getFields(), $convert_timestamp_from_gmt);
			}
		}
		$currentCache = $dataCacheService->getCachedData($record, $mappings);
		
		// Check 1: Reset flags if previousCache exists and differs from currentCache
		if(!empty($previousCache)) {
			$updatedCacheIds = $dataCacheService->compareCachedData($previousCache, $currentCache);
			$dataCacheService->resetAdjudicationFlags($updatedCacheIds);
		}
		
		// Check 2: Reset flags if currentCache differs from stored REDCap field data
		if(!empty($currentCache)) {
			$redcapData = $dataCacheService->getRedcapRecordData($record, $mappings);
			$cacheRedcapDiffIds = $dataCacheService->compareCacheWithRedcapData($currentCache, $redcapData, $mappings);
			$dataCacheService->resetAdjudicationFlags($cacheRedcapDiffIds);
		}

		$temporal_data = $dataFetchService->getTemporalData($record, $form_data, $event_id, $instance, $repeat_instrument);
		$fieldInfoCollection = FieldInfoCollection::fromParams(
			$proj,
			$mappings,
			$temporal_data,
			$day_offset,
			$day_offset_plusminus,
			$record
		);
		$request_field_array = $fieldInfoCollection->getFieldEventInfo();
		return [$currentCache, $request_field_array];
	}

	/**
	 *
	 * @return FhirClient
	 */
	public function getFhirClient() {
		global $userid, $project_id;
		$user_id = User::getUIIDByUsername($userid);
		$fhirClient = FhirClientFacade::getInstance($this->fhirSystem, $project_id, $user_id);
		return $fhirClient;
	}

	public function getFhirData($mrn, $mapping_list=[])
	{
		global $project_id;

		try {
			$fhirClient = $this->getFhirClient();
			$recordAdapter = new RecordAdapter($fhirClient);
			$metadataSource = self::getFhirMetadataSource($project_id);
	
			// listen for notifications from the FhirClient

			$fhirClient->attach($recordAdapter, FhirClient::NOTIFICATION_ENTRIES_RECEIVED);
			$fhirClient->attach($recordAdapter, FhirClient::NOTIFICATION_ERROR);
			// start the fetching process
            $mappingGroups = FhirMappingGroup::makeGroups($metadataSource, $mapping_list);
            foreach ($mappingGroups as $mappingGroup) {
                $fhirClient->fetchData($mrn, $mappingGroup);
            }
		} catch (\Exception $e) {
			$recordAdapter->addError($e);
		}finally {
			// $data1 = $recordAdapter->getData();
			return $recordAdapter;
		}
	}

	/**
	 * @return FhirMetadataSource
	 */
	public static function getFhirMetadataSource($project_id)
	{
		$fhirSystem = FhirSystem::fromProjectId($project_id);
		$fhirVersionManager = FhirVersionManager::getInstance($fhirSystem);
		$metadataSource = $fhirVersionManager->getFhirMetadataSource();
		$metadataSource = new FhirMetadataVandyDecorator($metadataSource);
		$metadataSource = new FhirMetadataCapabilitiesDecorator($metadataSource, $fhirVersionManager);
		$metadataSource = new FhirMetadataEmailDecorator($metadataSource);
		$metadataSource = new FhirMetadataAdverseEventDecorator($metadataSource);
		$metadataSource = new FhirMetadataCdpDecorator($metadataSource); // do not use encounters
		$metadataSource = new FhirMetadataCustomDecorator($metadataSource); // apply custom metadata
		// TODO: remove the properies decorator. instead return a lookup array with list of properties available per fhir category/resource
		// $metadataSource = new FhirMetadataPropertiesDecorator($fhirVersionManager, $metadataSource); // apply custom metadata

		return $metadataSource;
	}

	public function getCachedDataForExternalSource($record, $sourceFieldNames) {
		$list = [];
		$sourceFieldNames = array_unique($sourceFieldNames);
		if(empty($sourceFieldNames)) return $list;
		$placeholders = dbQueryGeneratePlaceholdersForArray($sourceFieldNames);
		$params = array_merge([$this->project_id, $record], $sourceFieldNames);
		$sql = "SELECT d.*, m.external_source_field_name,
				CASE 
					WHEN d.adjudicated = 1 OR d.exclude = 1 THEN TRUE
					ELSE FALSE
				END AS processed
			FROM redcap_ddp_records_data AS d
			LEFT JOIN redcap_ddp_records AS r ON r.mr_id = d.mr_id
			LEFT JOIN redcap_ddp_mapping AS m ON d.map_id = m.map_id
			WHERE r.project_id = ?
			AND r.record = ?
			AND m.external_source_field_name IN ($placeholders)";
		$result = db_query($sql, $params);
		while($row = db_fetch_assoc($result)) {
			$list[$row['md_id']] = $row;
		}
		return $list;
	}

	public function makeAdjudicationManager(
	) {
		$project = new Project($this->project_id);

		$adjudicationManager = new AdjudicationManager(
			$dataRetrievalService = new DataRetrievalService($project, $this),
			$dataProcessingService = new DataProcessingService($project),
			$adjudicationTrackingService = new AdjudicationTrackingService($this->project_id),
			$userInterfaceService = new UserInterfaceService(),
			$preSelectionService = new PreSelectionService($project),
			$dataOrganizationService = new DataOrganizationService($project),
			$dataNormalizationService = new DataNormalizationService($project),
			$databaseService = new DatabaseService($this->project_id),
			$errorHandlingService = new ErrorHandlingService(),
		);

		// Return the manager for further use
		return $adjudicationManager;
	}

	/**
	 * CLEAN HTML AND TRUNCATE VALUE FOR JAVASCRIPT INJECTION
	 * Strip HTML tags, escape JS characters, and truncate text to prevent issues in JavaScript context
	 */
	private function cleanValueForJs($value, $maxLength = 100)
	{
		// Strip HTML tags
		$cleanValue = strip_tags($value);
		
		// Truncate if too long
		if (strlen($cleanValue) > $maxLength) {
			$cleanValue = substr($cleanValue, 0, $maxLength) . '...';
		}
		
		// Escape JavaScript-breaking characters
		$cleanValue = str_replace(
			array('\\', "'", '"', "\n", "\r", "\t"),
			array('\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'),
			$cleanValue
		);
		
		return $cleanValue;
	}

	/**
	 * GET NEW ITEM COUNT FOR A RECORD
	 * Returns integer for count (if not exists, returns null)
	 */
	private function getNewItemCount($record)
	{
		$item_count = null;
		$sql = "select item_count from redcap_ddp_records
				where record = '" . db_escape($record) . "' and project_id = " . $this->project_id;
		$q = db_query($sql);
		if ($q && db_num_rows($q)) {
			$item_count = db_result($q, 0);
		}
		// Return count is exists, else return null
		return (is_numeric($item_count) ? $item_count : null);
	}


	/**
	 * LOG ALL THE SOURCE DATA POINTS (MD_ID'S) VIEWED BY THE USER
	 * Returns nothing.
	 */
	public function logDataView($external_id_value=null, $md_ids_viewed=array())
	{
		// Keep count of data points logged
		$num_logged = 0;
		if (!empty($md_ids_viewed))
		{
			// Get the timestamp and external source field names for the md_id's
			$md_ids_data = array();
			$sql = "select d.md_id, m.external_source_field_name, d.source_timestamp
					from redcap_ddp_records_data d, redcap_ddp_mapping m
					where m.map_id = d.map_id and d.md_id in (".prep_implode(array_unique($md_ids_viewed)).")
					and m.project_id = ".$this->project_id."";
			$q = db_query($sql);
			if (db_num_rows($q) < 1) return $num_logged;
			while ($row = db_fetch_assoc($q)) {
				$md_ids_data[$row['md_id']] = array('field'=>$row['external_source_field_name'], 'timestamp'=>$row['source_timestamp']);
			}
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".db_escape($external_id_value)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($md_ids_data as $md_id=>$attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp, md_id)
							values ($ml_id, '".db_escape($attr['field'])."', ".checkNull($attr['timestamp']).", $md_id)";
					if (db_query($sql)) $num_logged++;
				}
				// If somehow no data points were logged, then remove this instance from logging table
				if ($num_logged == 0) {
					$sql = "delete from redcap_ddp_log_view where ml_id = $ml_id";
					db_query($sql);
				}
			}
		}
		// Return count of data points that were logged as having been viewed
		return $num_logged;
	}


	/**
	 * Determine if a date[time] falls within a window of time by using a base date[time] +- offset
	 */
	private static function dateInRange($dateToCheck, $dateBase, $dayOffset, $day_offset_plusminus)
	{
		// Convert day_offset to seconds for comparison
		$dayOffsetSeconds = $dayOffset*86400;
		// Check if in range, which is dependent upon offset_plusminus value
		if ($day_offset_plusminus == '+-') {
			$diff = abs(strtotime($dateToCheck) - strtotime($dateBase));
			return ($diff <= $dayOffsetSeconds);
		} elseif ($day_offset_plusminus == '+') {
			$diff = strtotime($dateToCheck) - strtotime($dateBase);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} elseif ($day_offset_plusminus == '-') {
			$diff = strtotime($dateBase) - strtotime($dateToCheck);
			return ($diff <= $dayOffsetSeconds && $diff >= 0);
		} else {
			return false;
		}
	}

	/**
	 * PARSE AND SAVE DATA SUBMITTED AFTER BEING ADJUDICATED
	 */
	public function saveAdjudicatedData($record, $event_id, $form_data)
	{
		global $table_pk, $table_pk_label, $Proj, $auto_inc_set, $lang;

		// If using record auto-numbering and record does not exist yet, then update $record with next record name (in case current one was already saved by other user)
		if (isset($form_data['rtws_adjud-record_exists']) && $form_data['rtws_adjud-record_exists'] == '0' && $auto_inc_set) {
			$record = DataEntry::getAutoId();
		}
		if (isset($form_data['md_ids_out_of_range']) && trim($form_data['md_ids_out_of_range']) != '') {
			$md_ids_out_of_range = array_unique(explode(",", $form_data['md_ids_out_of_range']));
		}
		unset($form_data['rtws_adjud-record_exists'], $form_data['md_ids_out_of_range']);
		
		// collect the FHIR statistics for adjudicated data
		// $fhirStatsCollector = new FhirStatsCollector($this->project_id, FhirStatsCollector::REDCAP_TOOL_TYPE_CDP);
		$ehr_id = ($this->fhirSystem instanceof FhirSystem) ? $this->fhirSystem->getEhrId() : null;
		
		// Parse submitted form data into array of new/changed values. Save with record, event_id, field as 1st, 2nd, and 3rd-level array keys.
		$data_to_save = $md_ids = array();
		foreach ($form_data as $key=>$val) {
			// Explode the key
			list ($md_id, $rc_event, $rc_field, $instance) = explode("-", $key, 5);
			if ($instance < 1 || !is_numeric($instance)) $instance = 1;
			// Add md_id to array so we can set this value as "adjudicated" in the mapping_data table
			if ($md_id != '') $md_ids[] = $md_id;
			// Add to rc data array
			$val = str_replace("\t", "", $val);
			if ($val != '') {
				// If value is blank, then do not save it (only sent it to mark it as adjudicated)
				if ($Proj->isCheckbox($rc_field)) {
					$data_to_save[$record][$rc_event][$instance]["__chk__{$rc_field}_RC_".DataEntry::replaceDotInCheckboxCoding($val)] = $val;
				} else {
					$data_to_save[$record][$rc_event][$instance][$rc_field] = $val;
				}
				// notify that data has been collected for being saved to subscribers
				$this->notify(self::NOTIFICATION_DATA_COLLECTED_FOR_SAVING, [
					'ehr_id' => $ehr_id,
					'project_id' => $this->project_id,
					'record_id' => $record,
					'redcap_event' => $rc_event,
					'redcap_field' => $rc_field,
					'increment' => 1
				]);
			}
		}
		
		
		$md_ids = array_unique($md_ids);

		// Get the REDCap field name and event_id of the external identifier
		list ($rc_field_external, $rc_event_external) = $this->getMappedIdRedcapFieldEvent();

		// Keep count of number of items saved
		$itemsSaved = 0;
		
		// Initialize simplified data structure for efficient collection
		$simplified_data = array();
		
		// Anonymous function to collect adjudicated field data in flat structure
		$collectAdjudicatedField = function($event_id, $instance, $field, $data) use (&$simplified_data) {
			$simplified_data[] = [
				'event_id' => $event_id,
				'instance' => $instance,
				'field' => $field,
				'data' => $data
			];
		};
		
		// Note the current instance, if any
		$currentInstanceOrig = (is_numeric($_GET['event_id']) && is_numeric($_GET['instance'])) ? $_GET['instance'] : null;

		// Loop through each event of data and save it using DataEntry::saveRecord() function
		foreach ($data_to_save as $this_record=>$event_data) {
			foreach ($event_data as $this_event_id=>$idata) {
				foreach ($idata as $this_instance=>$field_data) {
					// Simulate new Post submission (as if submitted via data entry form)
					$_POST = array_merge(array($table_pk=>$this_record), $field_data);
					// Need event_id and instance in query string for saving properly
					$_GET['event_id'] = $this_event_id;
					$_GET['instance'] = $this_instance;
					// Delete randomization field values and log it
					DataEntry::saveRecord($this_record);
					// notify when a record has been saved in a specific event
					$this->notify(self::NOTIFICATION_DATA_SAVED, ['data' => $this_record, 'event_id'=>$this_event_id, 'instance' =>$this_instance]);
					
					// Add javascript to string for eval'ing (only on data entry form)
					if ($event_id != '') {
						if ($this_event_id == $event_id 
							&& ($currentInstanceOrig == null || ($currentInstanceOrig != null && $currentInstanceOrig == $this_instance))
						) {
							## CURRENT EVENT
							// Loop through all fields in event
							foreach ($field_data as $this_field=>$this_value) {
								// Increment number of items saved and collect simplified data
								if ($this_field != $table_pk && $this_field != $rc_field_external) {
									$itemsSaved++;
									// Pass raw value to simplified data (JSON encoding will handle escaping safely)
									$collectAdjudicatedField($event_id, $this_instance, $this_field, $this_value);
								}
							}
						}
					}
				}
			}
		}
		$response = ['itemsSaved'=> $itemsSaved, 'record' => $data_to_save, 'data' => $simplified_data];
		// notify that data has been saved to subscribers
		$this->notify(self::NOTIFICATION_DATA_SAVED_FOR_ALL_EVENTS, $response);

		// Set all adjudicated items as "adjudicated" in mapping_data table (include all values for a given item/field that was adjudicated)
		$sql = "";
		if (!empty($md_ids)) 
		{
			$sql = "update redcap_ddp_records_data a, redcap_ddp_mapping b,
					redcap_ddp_mapping c, redcap_ddp_records_data d, redcap_ddp_records e
					set d.adjudicated = 1 where a.map_id = b.map_id and b.field_name = c.field_name and b.event_id = c.event_id
					and d.map_id = c.map_id and d.mr_id = e.mr_id and e.mr_id = a.mr_id
					and a.md_id in (" . prep_implode($md_ids) . ")";
			if (!empty($md_ids_out_of_range)) {
				$sql .= " and d.md_id not in (" . prep_implode($md_ids_out_of_range) . ")";
			}
			$q = db_query($sql);
			
			// Update item count efficiently after adjudication
			$this->updateItemCountAfterAdjudication($record, $md_ids);
		}

		return json_encode($response);
	}

	/**
	 * Update the item count in redcap_ddp_records after adjudication
	 * 
	 * This method decrements the item count by the number of items that were just adjudicated,
	 * providing an efficient way to maintain accurate counts without reprocessing all data.
	 * 
	 * @param string $record The record ID
	 * @param array $adjudicated_md_ids Array of mapping data IDs that were adjudicated
	 * @return bool True if update was successful, false otherwise
	 */
	private function updateItemCountAfterAdjudication($record, $adjudicated_md_ids)
	{
		// Validate inputs
		if (empty($record) || empty($adjudicated_md_ids) || !is_array($adjudicated_md_ids)) {
			return false;
		}
		
		// Count unique adjudicated items
		$itemsAdjudicated = count(array_unique($adjudicated_md_ids));
		
		// Update database - decrement count, ensuring it never goes below 0
		$sql = "UPDATE redcap_ddp_records 
				SET item_count = GREATEST(0, item_count - ?) 
				WHERE record = ? AND project_id = ?";
		
		$result = db_query($sql, [$itemsAdjudicated, $record, $this->project_id]);
		
		// Log the update for debugging if needed
		error_log("DDP: Updated item count for record '$record' - decremented by $itemsAdjudicated items");
		
		return ($result !== false);
	}

	public static function getMappedFhirResourceFromFieldName($project_id, $field_name, $event_id=null)
	{
		$fhirMetadata = self::getFhirMetadataSource($project_id);
		$fhirMetadataList = $fhirMetadata->getList();
		// search the resource type using the external fields and the mapping table
		$query_string = sprintf(
			"SELECT * FROM redcap_ddp_mapping
			WHERE project_id=%u
			AND field_name='%s'",
			$project_id,
			db_real_escape_string($field_name)
		);
		if($event_id) $query_string .= " AND event_id={$event_id}";
		$result = db_query($query_string);
		if($row = db_fetch_assoc($result)) {
			$external_source_field_name = @$row['external_source_field_name'];
			$fhir_external_field_data = @$fhirMetadataList[$external_source_field_name];
			$category = $fhir_external_field_data['category'];
			return $category;
		}
		return;
	}

	/**
	 * log FHIR adjudication statistic
	 *
	 * @param FhirStatsCollector $fhirStatsCollector
	 * @param array $record record in a format compatible with Records::saveData
	 * @return void
	 */
	public static function logFhirStatsUsingRecord($fhirStatsCollector, $record)
	{
		$project_id = $fhirStatsCollector->getProjectId();
		// helper to add an entry to the log collector
		$addFieldName = function($record_id, $fieldName, $event_id) use($project_id, $fhirStatsCollector) {
			if($category = self::getMappedFhirResourceFromFieldName($project_id, $fieldName, $event_id)) {
				$fhirStatsCollector->addEntry($record_id, $category, $value=1);
			}
		};
		// helper for repeated instances
		$countInstances = function($record_id, $repeatInstances) use($addFieldName){
			foreach ($repeatInstances as $event_id => $forms) {
				foreach ($forms as $formName => $instances) {
					foreach ($instances as $instanceNumber => $data) {
						foreach ($data as $fieldName => $value) {
							$addFieldName($record_id, $fieldName, $event_id);
						}
					}
				}
			}
		};
		// main function
		$collectStatistics = function($record) use($addFieldName, $countInstances) {
			foreach ($record as $record_id => $events) {
				foreach ($events as $event_id => $data) {
					if($data=='repeat_instances') {
						$repeatInstances = $data;
						$countInstances($record_id, $repeatInstances);
						continue;
					}
					
					foreach ($data as $fieldName => $value) {
						$addFieldName($record_id, $fieldName, $event_id);
					}
				}
			}
		};
		$collectStatistics($record);
		$fhirStatsCollector->logEntries();
	}


	/**
	 * GET LIST OF FIELDS ALREADY MAPPED TO EXTERNAL SOURCE FIELDS
	 * Return array of fields with external source field as 1st level key, REDCap event_id as 2nd level key,
	 * REDCap field name as 3rd level key,and sub-array of attributes (temporal_field, is_record_identifier).
	 */
	public function getMappedFields()
	{		
		// Make sure Project Attribute class has instantiated the $Proj object
		if ($this->project_id === 0) {
			return array();
		} else {
			$Proj = new Project($this->project_id);
		}

		// If class variable is null, then create mapped field array
		if ($this->field_mappings === null) {
			// Put fields in array
			$this->field_mappings = array();
			// Query table
			$sql = "select * from redcap_ddp_mapping where project_id = ".$this->project_id."
					order by is_record_identifier desc, external_source_field_name, event_id, field_name, temporal_field";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q))
			{
				// If event_id is orphaned, then skip it
				if (!isset($Proj->eventInfo[$row['event_id']])) continue;
				// If field is orphaned, then skip it
				if (!isset($Proj->metadata[$row['field_name']])) continue;
				// Initialize sub-array, if not initialized
				if (!isset($this->field_mappings[$row['external_source_field_name']])) {
					$this->field_mappings[$row['external_source_field_name']] = array();
				}
				// Add to array
				$this->field_mappings[$row['external_source_field_name']][$row['event_id']][$row['field_name']] = array(
					'map_id' => $row['map_id'],
					'is_record_identifier' => $row['is_record_identifier'],
					'temporal_field' => $row['temporal_field'],
					'preselect' => $row['preselect']
				);
			}
		}
		// Return the array of field mappings
		return $this->field_mappings;
	}


	/**
	 * RETURN ARRAY OF MAPPED REDCAP FIELDS IF INSTRUMENT HAS ANY REDCAP FIELDS MAPPED TO SOURCE FIELDS FOR THIS FORM/EVENT
	 * Note: This also includes all temporal date/time fields used for mapped fields.
	 */
	public function formEventMappedFields($form_name, $event_id)
	{
		global $Proj;
		// Put fields in array
		$fields = array();
		// Query table
		$fields_keys = isset($Proj->forms[$form_name]) ? array_keys($Proj->forms[$form_name]['fields']) : array();
		$sql = "select field_name, temporal_field from redcap_ddp_mapping where project_id = ".$this->project_id."
				and event_id = $event_id and field_name in (" . prep_implode($fields_keys) . ")";
		$q = db_query($sql);
		if($q !== false)
		{
			while ($row = db_fetch_assoc($q))
			{
				// Add field to array, if on this form
				if (isset($Proj->forms[$form_name]['fields'][$row['field_name']])) {
					$fields[] = $row['field_name'];
				}
				// Add temporal field to array, if not null and on this form
				if ($row['temporal_field'] != '' && isset($Proj->forms[$form_name]['fields'][$row['temporal_field']])) {
					$fields[] = $row['temporal_field'];
				}
			}
		}
		// Make all unique
		$fields = array_unique($fields);
		// Return array
		return $fields;
	}

	/**
	 * Initialize the new Vue-based adjudication modal system
	 * This method loads the necessary assets and creates the global function for opening the modal
	 */
	public function initializeAdjudicationModal($record = null)
	{
		// Make sure RTWS is enabled and user can adjudicate
		if (!(((self::isEnabledInSystem() && self::isEnabled($this->project_id)) 
			|| (self::isEnabledInSystemFhir() && self::isEnabledFhir($this->project_id))) && $this->userHasAdjudicationRights())) return;
		
		// Use static variable to prevent duplicate loading of assets
		static $modal_assets_loaded = false;
		
		if (!$modal_assets_loaded) {
			$lang = Language::getLanguage();
			$project = new Project($this->project_id);
			$realtime_webservice_offset_days = $project->project['realtime_webservice_offset_days'] ?? '365';
            $realtime_webservice_offset_plusminus = $project->project['realtime_webservice_offset_plusminus'] ?? '+-';
			$instructions = Language::tt('ws_166', ['default' => 'Review the data retrieved from the source system and select the values you want to import into your REDCap project.']);
			$rangeTex = Language::tt('ws_191', [
				'replacements' => [
					'min_days' => self::DAY_OFFSET_MIN,
					'min_minutes' => (ceil(self::DAY_OFFSET_MIN*60*24)), // convert to minutes
					'max_days' => self::DAY_OFFSET_MAX,
				],
			]);
			// Get the REDCap field name mapped to the external source identifier field
			list ($rc_identifier_field, $rc_identifier_event) = $this->getMappedIdRedcapFieldEvent();
			$hasPreviewFields = (count($this->getPreviewFields()) > 0);
			
			// Load the adjudication modal system inline with PHP-built imports
			?>
			<script type="module">
				import {useModal, useToaster, EventBus, useAssetLoader} from '<?= APP_PATH_JS.'Composables/index.es.js.php' ?>'
				
				// Load the AdjudicationModal and PreviewModal classes
				import { AdjudicatedDataApplicator } from '<?= APP_PATH_JS ?>EHR/AdjudicatedDataApplicator.js'
				import { AdjudicationModal } from '<?= APP_PATH_JS ?>EHR/AdjudicationModal.js'
				import { PreviewModal } from '<?= APP_PATH_JS ?>EHR/PreviewModal.js'
				
				// Initialize modals
				const modal = useModal()
				const toaster = useToaster()
				// const dataApplicator = new AdjudicatedDataApplicator()
				
				// Create adjudication modal with default configuration
				const adjudicationModal = new AdjudicationModal(modal, toaster, {
					// dataApplicator: dataApplicator,
					sourceSystemName: 'External System',
					recordLabel: 'Record ID',
					dayOffsetMin: 0.01,
					dayOffsetMax: 365,
					dayOffset: '<?= $realtime_webservice_offset_days ?>',
					dayOffsetPlusMinus: '<?= $realtime_webservice_offset_plusminus ?>',
					saveEndpoint: 'DynamicDataPull/save.php',
					instructionsText: '<?= $instructions ?>',
					rangeText: '<?= $rangeTex ?>',
					autoSave: true,
					reloadOnSuccess: true,
					showSaveProgress: true,
					successDelay: 2000,
				})
				
				// Create preview modal for MRN field preview functionality
				const previewModal = new PreviewModal(modal, {
					sourceSystemName: 'External System',
					recordLabel: 'MRN',
					previewEndpoint: 'DynamicDataPull/preview.php',
					hasPreviewFields: <?= $hasPreviewFields ? 'true' : 'false' ?>,
					onCancel: function() {
						// Focus back to the MRN field when cancelled, matching original behavior
						const mrnField = document.querySelector('form#form :input[name="<?= $rc_identifier_field ?>"]');
						if (mrnField) {
							setTimeout(() => mrnField.focus(), 100);
						}
					}
				})
				
				// Make toaster globally available
				window.toaster = toaster
				
				/**
				 * Global function to open the adjudication modal
				 */
				async function openAdjudicationModal(recordId, options = {}) {
					try {
						const modalOptions = {
							hasTemporalFields: true,
							dayOffset: <?= $realtime_webservice_offset_days ?>,
							dayOffsetPlusMinus: '<?= $realtime_webservice_offset_plusminus ?>',
							autoFetch: true,
							autoSave: true,
							...options
						}
						
						const result = await adjudicationModal.open(recordId, modalOptions)
						return result
						
					} catch (error) {
						console.error('Error opening adjudication modal:', error)
						
						if (window.toaster) {
							window.toaster.error(`Failed to open adjudication modal: ${error.message}`, { title: 'Error' })
						} else {
							alert(`Failed to open adjudication modal: ${error.message}`)
						}
						
						return { confirmed: false, saved: false, error: error }
					}
				}
				
				/**
				 * Legacy compatibility wrapper for triggerRTWSmappedField
				 */
				function triggerRTWSmappedField(recordName, autoOpenDialog = false, outputHtml = null, showExclusions = null, forceDataFetch = null) {
					console.log('triggerRTWSmappedField called with legacy compatibility mode')
					
					if (autoOpenDialog && recordName) {
						const record = typeof recordName === 'string' ? recordName.trim() : recordName
						openAdjudicationModal(record)
					} else {
						console.log('triggerRTWSmappedField: autoOpenDialog is false, modal not opened automatically')
					}
				}
				
				/**
				 * Legacy compatibility wrapper for openAdjudicationDialog
				 */
				function openAdjudicationDialog(recordName) {
					console.log('openAdjudicationDialog called with legacy compatibility mode')
					
					if (recordName) {
						openAdjudicationModal(recordName)
					} else {
						console.warn('openAdjudicationDialog: no record name provided')
					}
				}
				
				/**
				 * Global function to open the preview modal for MRN field
				 */
				async function openPreviewModal(identifier, options = {}) {
					try {
						const result = await previewModal.open(identifier, options)
						return result
						
					} catch (error) {
						console.error('Error opening preview modal:', error)
						
						if (window.toaster) {
							window.toaster.error(`Failed to open preview modal: ${error.message}`, { title: 'Error' })
						} else {
							alert(`Failed to open preview modal: ${error.message}`)
						}
						
						return false
					}
				}
				
				// Expose global functions
				window.REDCap = window.REDCap || {}
				window.REDCap.openAdjudicationModal = openAdjudicationModal
				window.REDCap.openPreviewModal = openPreviewModal
				
				// Also expose legacy functions for backward compatibility during migration
				window.triggerRTWSmappedField = triggerRTWSmappedField
				window.openAdjudicationDialog = openAdjudicationDialog
				
				/**
				 * Set the div in place in the form's blue/green context msg div for RTWS status messages
				 * This function was originally in DynamicDataPullAdjudicate.js
				 */
				function setRTWSContextMsgPlaceholder(text) {
					// Record Home Page
					const isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
					if (isEHRpage || (typeof page !== 'undefined' && page == 'DataEntry/record_home.php')) {
						const recordDisplayName = document.querySelector('#record_display_name');
						if (recordDisplayName) {
							const lastDiv = recordDisplayName.querySelector('div:last-child');
							if (lastDiv) {
								const rtws = document.createElement('div');
								rtws.id = 'RTWS_sourceDataCheck';
								rtws.style.marginTop = '2px';
								rtws.style.color = '#666';
								rtws.style.width = '320px';
								rtws.style.textAlign = 'right';
								rtws.innerHTML = text;
								lastDiv.appendChild(rtws);
							}
						}
					} else {
						// Get class of context msg div
						const contextMsgClass = (typeof record_exists !== 'undefined' && record_exists) ? 'blue' : 'darkgreen';
						// Add the "checking source system..." text at top of form
						const existingRTWS = document.querySelector('div#contextMsg #RTWS_sourceDataCheck');
						if (!existingRTWS) {
							// Get contents of context msg div
							const contextMsgDiv = document.querySelector('div#contextMsg .' + contextMsgClass);
							if (contextMsgDiv) {
								const contextMsgContents = contextMsgDiv.innerHTML;
								// Check if #RTWS_sourceDataCheck is already on page. If not, then add.
								contextMsgDiv.innerHTML = '<table cellspacing="0" style="width:100%;table-layout:fixed;"><tr><td id="RTWS_contextMsg_original">' + contextMsgContents + '</td>' +
										 '<td id="RTWS_sourceDataCheck" style="color:#666;width:320px;text-align:right;">' + text + '</td></tr></table>';
							}
						} else {
							const msgBox = document.querySelector('div#contextMsg #RTWS_sourceDataCheck #RTWS_sourceDataCheck_msgBox');
							if (!msgBox) {
								const rtws = document.querySelector('div#contextMsg #RTWS_sourceDataCheck');
								if (rtws) {
									rtws.innerHTML = text;
								}
							}
						}
					}
				}
				
				// Expose the context message function globally
				window.setRTWSContextMsgPlaceholder = setRTWSContextMsgPlaceholder
				
				<?php if ($rc_identifier_field): ?>
				/**
				 * Initialize MRN field event handling for preview functionality
				 * This replicates the original behavior from renderJsAdjudicationPopup
				 */
				document.addEventListener('DOMContentLoaded', function() {
					// Add change event handler to the MRN/identifier field
					const mrnField = document.querySelector('form#form input[name="<?= $rc_identifier_field ?>"], form#form select[name="<?= $rc_identifier_field ?>"], form#form textarea[name="<?= $rc_identifier_field ?>"]');
					
					if (mrnField) {
						mrnField.addEventListener('change', function() {
							// Trim the value
							this.value = this.value.trim();
							if (this.value.length == 0) return;
							
							<?php if ($hasPreviewFields): ?>
							// If user needs to re-connect with EHR, show standalone launch dialog
							const fhirLaunchModal = document.getElementById('fhir_launch_modal');
							if (fhirLaunchModal && typeof getCookie === 'function' && !getCookie('fhir-launch-stop-asking')) {
								if (typeof showFhirLaunchModal === 'function') {
									showFhirLaunchModal();
									return;
								}
							}
							<?php endif; ?>
							
							// Open preview modal with the identifier value
							const identifierValue = this.value;
							console.log('MRN field changed, opening preview modal for:', identifierValue);
							
							// Call the global preview modal function
							window.REDCap.openPreviewModal(identifierValue);
						});
					}
				});
				<?php endif; ?>
			</script>
			<?php
			
			$modal_assets_loaded = true;
		}
		
		// If a specific record is provided, also set up auto-check functionality
		if ($record !== null) {
			global $lang;
			$itemsToAdjudicate = $this->getNewItemCount($record);
			
			if ($itemsToAdjudicate === null) {
				$itemsToAdjudicate = 0; // Default to 0 if not yet determined
			}
			
			// Display appropriate message based on item count
			if ($itemsToAdjudicate == 0) {
				// No new items - use heredoc for cleaner HTML
				$recordEscaped = js_escape($record);
				$viewText = htmlspecialchars($lang['global_84']);
				$noItemsText = htmlspecialchars($lang['ws_174']);
				
				$newItemsText = <<<HTML
<i class="fas fa-info-circle fa-fw"></i>
<span style="color:#000066;">{$noItemsText}</span>
&nbsp;(<a href="javascript:;" style="margin:0 1px;font-size:11px;" onclick="window.REDCap.openAdjudicationModal('{$recordEscaped}');return false;">{$viewText}</a>)
HTML;
			} else {
				// There are 1 or more new items - use heredoc for cleaner HTML
				$recordEscaped = js_escape($record);
				$viewText = htmlspecialchars($lang['global_84']);
				$newItemsLabel = htmlspecialchars($lang['ws_175']);
				
				$newItemsText = <<<HTML
				<div id="RTWS_sourceDataCheck_msgBox" class="red" style="color:#C00000;text-align:center;font-weight:bold;">
					<span class="badgerc" style="font-size:12px;">{$itemsToAdjudicate}</span>
					<span>{$newItemsLabel}</span>
					<button class="jqbuttonmed" style="margin-left:10px;" onclick="window.REDCap.openAdjudicationModal('{$recordEscaped}');return false;">{$viewText}</button>
				</div>
				HTML;
			}
			
			// JavaScript to initialize the display - this runs after the modal system loads
			$newItemsTextEscaped = js_escape($newItemsText);
			echo <<<JAVASCRIPT
			<script type='text/javascript'>
			$(document).ready(function() {
				// Wait a moment for the modal system to initialize, then set context message
				setTimeout(function() {
					// Set context msg "new items" count at top of page
					var isEHRpage = (window.location.href.indexOf('/ehr.php') > -1);
					if (page == 'DataEntry/index.php' || isEHRpage || page == 'DataEntry/record_home.php') {
						if (record_exists && window.setRTWSContextMsgPlaceholder) {
							window.setRTWSContextMsgPlaceholder('{$newItemsTextEscaped}');
							$('#RTWS_sourceDataCheck_msgBox button').button();
						}
					}
				}, 200);
			});
			</script>
			JAVASCRIPT;
		}
	}


	/**
	 * RETURNS ARRAY OF ALL DATE/DATETIME FIELDS MAPPED WITH TEMPORAL FIELDS
	 * Array will contain event_id as first-level key and date field name as 2nd-level key
	 */
	private function getTemporalDateFieldsEvents()
	{
		$temporalDateFieldsEvents = array();
		$sql = "select distinct event_id, temporal_field from redcap_ddp_mapping
				where project_id = " . $this->project_id . " and temporal_field is not null";
		$q = db_query($sql);
		while ($row = db_fetch_assoc($q)) {
			$temporalDateFieldsEvents[$row['event_id']][$row['temporal_field']] = true;
		}
		return $temporalDateFieldsEvents;
	}


	/**
	 * DETERMINE IF DDP IS ENABLED AT THE SYSTEM-LEVEL
	 */
	public static function isEnabledInSystem()
	{
		$config = REDCapConfigDTO::fromDB();
		return boolval($config->realtime_webservice_global_enabled);
	}


	/**
	 * DETERMINE IF DDP ON FHIR IS ENABLED AT THE SYSTEM-LEVEL
	 */
	public static function isEnabledInSystemFhir()
	{
		$config = REDCapConfigDTO::fromDB();
		return boolval($config->fhir_ddp_enabled);
	}

	public static function isEnabled($project_id) {
		$project = new Project($project_id);
		$webservice_enabled = boolval($project->project['realtime_webservice_enabled'] ?? false);
		$webservice_type = $project->project['realtime_webservice_type'] ?? false;
		return $webservice_enabled && $webservice_type === self::WEBSERVICE_TYPE_CUSTOM;
	}
	public static function isEnabledFhir($project_id) {
		$project = new Project($project_id);
		$webservice_enabled = boolval($project->project['realtime_webservice_enabled'] ?? false);
		$webservice_type = $project->project['realtime_webservice_type'] ?? false;
		return $webservice_enabled && $webservice_type === self::WEBSERVICE_TYPE_FHIR;
	}


	/**
	 * DETERMINE IF RTWS IS ENABLED IN THE CURRENT PROJECT
	 */
	public function isEnabledInProject()
	{
		// Get global vars
		global $realtime_webservice_enabled, $realtime_webservice_type;
		if($this->project_id) {
			$project = new Project($this->project_id);
			$webservice_enabled = $project->project['realtime_webservice_enabled'];
			$webservice_type = $project->project['realtime_webservice_type'];
		}else {
			$webservice_enabled = $realtime_webservice_enabled;
			$webservice_type = $realtime_webservice_type;
		}
		// Return boolean
		return (isset($webservice_enabled) && $webservice_enabled && $webservice_type == self::WEBSERVICE_TYPE_CUSTOM);
	}


	/**
	 * DETERMINE IF RTWS IS ENABLED IN THE CURRENT PROJECT
	 */
	public function isEnabledInProjectFhir()
	{
		// Get global vars
		global $realtime_webservice_enabled, $realtime_webservice_type;
		if($this->project_id) {
			$project = new Project($this->project_id);
			$webservice_enabled = $project->project['realtime_webservice_enabled'];
			$webservice_type = $project->project['realtime_webservice_type'];
		}else {
			$webservice_enabled = $realtime_webservice_enabled;
			$webservice_type = $realtime_webservice_type;
		}
		// Return boolean
		return (isset($webservice_enabled) && $webservice_enabled && $webservice_type == self::WEBSERVICE_TYPE_FHIR);
	}


	/**
	 * DETERMINE IF USER HAS RTWS MAPPING PRIVILEGES
	 */
	public function userHasMappingRights()
	{
		// Get global vars
		global $user_rights;
		// Return boolean
		return ($user_rights['realtime_webservice_mapping'] == '1');
	}


	/**
	 * DETERMINE IF USER HAS RTWS ADJUDICATION PRIVILEGES
	 */
	public function userHasAdjudicationRights($checkUserAccessWebService=false)
	{
		// Get global vars
		global $user_rights, $realtime_webservice_type;
		// Check project-level user rights first
		if (defined("PROJECT_ID") && $user_rights['realtime_webservice_adjudicate'] != '1') return false;
		// Return boolean (check user access web service, if applicable)
		if ($checkUserAccessWebService && $realtime_webservice_type == self::WEBSERVICE_TYPE_CUSTOM) {
			return $this->userHasAdjudicationRightsWebService();
		} elseif ($checkUserAccessWebService && $realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR) {
			return $this->userHasAdjudicationRightsWebService();
		} else {
			return true;
		}
	}


	/**
	 * CALL "USER ACCESS" WEB SERVICE TO GET "1" (user can adjudicate data from source system) or "0" (user cannot adjudicate data)
	 * Return TRUE if web service returns "1", else return FALSE.
	 * The web service will be called only once per user per project per session and will store a value in the session for checking afterward.
	 * The session variable will be project-specific so that it will be call whenever first accessing the project, which will allow
	 * admins to possibly build project-specific checks (e.g. IRB number) when first accessing a DDP-enabled project.
	 */
	public function userHasAdjudicationRightsWebService()
	{
		// Get global var
		global $realtime_webservice_url_user_access, $fhir_url_user_access, $realtime_webservice_type;
		
		// If user access web service URL is not defined, then always return TRUE
		if ($realtime_webservice_type == self::WEBSERVICE_TYPE_CUSTOM && trim($realtime_webservice_url_user_access) == '') 	return true;
		if ($realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR   && trim($fhir_url_user_access) == '') 				return true;		

		// Set session variable name
		$session_var_name = 'ddp_user_access_'.$this->project_id;

		// If user has session variable set to 0 or 1, then we've already checked the user web service during this session
		if (!isset($_SESSION[$session_var_name]))
		{
			// CALL WEB SERVICE
			// Set parameters for request
			$params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL);
			// Determine if we're calling the DDP custom service or the DDP FHIR service
			$webservice_url = ($realtime_webservice_type == self::WEBSERVICE_TYPE_CUSTOM) ? $realtime_webservice_url_user_access : $fhir_url_user_access;
			// Call the URL as POST request
			$response = http_post($webservice_url, $params, 30);
			// Set session value and return true if web service returned "1"
			$_SESSION[$session_var_name] = ($response !== false && trim($response."") === '1') ? '1' : '0';
		}
		
		// Return true if has session value of "1"
		return ($_SESSION[$session_var_name] == '1');
	}


	/**
	 * DETERMINE IF MAPPING HAS BEEN SET UP FOR PROJECT
	 * Check redcap_ddp_mapping table to see if any fields have been mapped.
	 */
	public function isMappingSetUp()
	{
		// Return boolean
		return (self::getMappedIdFieldExternal($this->project_id) !== false);
	}


	/**
	 * GET THE EXTERNAL FIELD NAME FOR RECORD IDENTIFIER FIELD MAPPED TO THE REDCAP FIELD IN THE PROJECT
	 */
	public static function getMappedIdFieldExternal($project_id)
	{
		// Query table
		$sql = "SELECT external_source_field_name FROM redcap_ddp_mapping
				WHERE project_id = ? AND is_record_identifier = 1 LIMIT 1";
		$q = db_query($sql, [$project_id]);
		// Return boolean
		return (db_num_rows($q) > 0 ? db_result($q, 0) : false);
	}


	/**
	 * GET THE REDCAP FIELD NAME AND EVENT_ID FOR RECORD IDENTIFIER FIELD MAPPED TO THE EXTERNAL FIELD IN THE PROJECT
	 */
	public function getMappedIdRedcapFieldEvent()
	{
		// Query table
		$sql = "select field_name, event_id from redcap_ddp_mapping
				where project_id = ".$this->project_id." and is_record_identifier = 1 limit 1";
		$q = db_query($sql);
		// Return boolean
		if (db_num_rows($q) > 0) {
			return array(db_result($q, 0, 'field_name'), db_result($q, 0, 'event_id'));
		} else {
			return false;
		}
	}


	/**
	 * EXCLUDE A SOURCE VALUE for a given record during the Adjudication process
	 */
	public function excludeValue($md_id, $exclude)
	{
		global $lang;
		// Make sure we have all the values we need, else return error
		if (($exclude != '0' && $exclude != '1') || !is_numeric($md_id)) return '0';
		// Update table
		$sql = "update redcap_ddp_records_data set exclude = $exclude where md_id = $md_id";
		$q = db_query($sql);
		// Log the event
		if ($q) {
			$log_description = ($exclude) ? "Exclude source value (DDP)" : "Remove exclusion for source value (DDP)";
			Logging::logEvent($sql, "redcap_ddp_records_data", "MANAGE", $md_id, "md_id = $md_id", $log_description);
		}
		// Return label text if true, else '0'
		return ($q ? ($exclude
						? RCView::img(array('src'=>'plus2.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_88']))
						: RCView::img(array('src'=>'cross.png', 'class'=>'opacity50', 'title'=>$lang['dataqueries_87']))
					  )
				   : '0');
	}


	/**
	 * GET EXCLUDED VALUES
	 * Return array of md_ids with values already excluded for the given record
	 */
	private function getExcludedValues($record, $map_ids=array())
	{
		// Put all values in array with md_id as array key
		$excluded_values_by_md_id = array();
		if (!empty($map_ids)) {
			$sql = "select d.md_id, d.source_value, d.source_value2
					from redcap_ddp_records r, redcap_ddp_records_data d
					where d.mr_id = r.mr_id and d.map_id in (" . prep_implode(array_unique($map_ids)) . ")
					and r.record = '" . db_escape($record) . "' and r.project_id = " . $this->project_id . "
					and exclude = 1";
			$q = db_query($sql);
			while ($row = db_fetch_assoc($q)) 
			{
				$use_mcrypt = ($row['source_value2'] == '');
				$source_value = $use_mcrypt ? $row['source_value'] : $row['source_value2'];
				$excluded_values_by_md_id[$row['md_id']] = decrypt($source_value, self::DDP_ENCRYPTION_KEY, $use_mcrypt);
			}
		}
		return $excluded_values_by_md_id;
	}



	/**
	 * GET MD_ID'S OF VALUES THAT HAVE *NOT* BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getNonAdjudicatedValues($record, $map_ids=array())
	{
		return $this->getCachedData($record, $map_ids, $adjudicated=false);
	}


	/**
	 * GET MD_ID'S OF VALUES THAT HAVE BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getAdjudicatedValues($record, $map_ids=[])
	{
		return $this->getCachedData($record, $map_ids, $adjudicated=true);
	}

		/**
	 * GET MD_ID'S OF VALUES THAT HAVE BEEN ADJUDICATED YET
	 * Return array of md_ids as keys for the given record
	 */
	private function getCachedData($record, $map_ids=[], $adjudicated=null)
	{
		$list = [];
        $uniqueMapIDs = array_unique($map_ids);
		if (!empty($uniqueMapIDs)) {
			$placeholders = prep_implode($uniqueMapIDs);
			
			$params = [$record, $this->project_id];
			$sql = "SELECT d.md_id FROM redcap_ddp_records r, redcap_ddp_records_data d
					WHERE d.mr_id = r.mr_id AND d.map_id IN ($placeholders)
					AND r.record = ? AND r.project_id = ?";
            if(!is_null($adjudicated)) {
				if($adjudicated == true) {
					// data is adjudicated if adjudicated OR exclude are true
					$sql .= " AND adjudicated = 1 OR exclude = 1";
				}else {
					// data is not adjudicated if not adjudicated AND exclude are false
					$sql .= " AND adjudicated = 0 AND exclude = 0";
				}
            }
			$q = db_query($sql, $params);
			while ($row = db_fetch_assoc($q)) {
				$list[$row['md_id']] = true;
			}
		}
		return $list;
	}


	/**
	 * GET TIME OF LAST DATA FETCH FOR A RECORD
	 * Return timestamp or NULL, if record data has not been cached yet.
	 */
	private function getLastFetchTime($record, $returnInAgoFormat=false)
	{
		global $lang;
		$sql = "select updated_at from redcap_ddp_records
				where project_id = " . $this->project_id . " and record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			$ts = db_result($q, 0);
			if(is_null($ts)) return null;
			// If we're returning the time in "X hours ago" format, then convert it, else return as is
			if ($returnInAgoFormat) {
				// If timestamp is NOW, then return "just now" text
				if ($ts == NOW) return $lang['ws_176'];
				// First convert to minutes
				$ts = (strtotime(NOW) - strtotime($ts))/60;
				// Return if less than 60 minutes
				if ($ts < 60) return ($ts < 1 ? $lang['ws_177'] : RCView::interpolateLanguageString(floor($ts) == 1 ? $lang['ws_178'] : $lang['ws_179'], [floor($ts)]));
				// Convert to hours
				$ts = $ts/60;
				// Return if less than 24 hours
				if ($ts < 24) return RCView::interpolateLanguageString(floor($ts) == 1 ? $lang['ws_180'] : $lang['ws_181'], [floor($ts)]);
				// Convert to days and return
				$ts = $ts/24;
				return RCView::interpolateLanguageString(floor($ts) == 1 ? $lang['ws_182'] : $lang['ws_183'], [floor($ts)]);
			}
			// Return value
			return $ts;
		} else {
			return null;
		}
	}


	/**
	 * GET MR_ID FOR A RECORD
	 * Return mr_id primary key for a record in this project. If not exists yet in table, then insert it.
	 */
	private function getMrId($record)
	{
		$sql = "select mr_id from redcap_ddp_records
				where project_id = " . $this->project_id . " and record = '" . db_escape($record) . "' limit 1";
		$q = db_query($sql);
		if (db_num_rows($q)) {
			return db_result($q, 0);
		} else {
			$sql = "insert into redcap_ddp_records (project_id, record)
					values (" . $this->project_id . ", '" . db_escape($record) . "')";
			return (db_query($sql) ? db_insert_id() : false);
		}
	}


	/**
	 * GET ANY "PREVIEW" SOURCE FIELDS THAT HAVE BEEN MAPPED
	 * Return array of source fields that have been designated as "preview" fields
	 */
	public function getPreviewFields()
	{
		$sql = "select field1, field2, field3, field4, field5
				from redcap_ddp_preview_fields where project_id = " . $this->project_id;
		$q = db_query($sql);
		if (db_num_rows($q)) {
			// Remove all blank instances
			$preview_fields = db_fetch_assoc($q);
			foreach ($preview_fields as $key=>$field) {
				if ($field == '') unset($preview_fields[$key]);
			}
			return array_values($preview_fields);
		} else {
			return array();
		}
	}

	/**
	 * OUTPUT THE EXPLANATION TEXT FOR CDP or DDP FOR DISPLAYING IN AN INFORMATION POPUP
	 */
	public static function getExplanationText($fhir=false)
	{
		return $fhir ? static::getCdpExplanationText() : static::getDdpExplanationText();
	}


	/**
	 * explanation text for CDP projects
	 *
	 * @return string
	 */
	public static function getCdpExplanationText()
	{
		global $lang, $fhir_custom_text, $fhir_user_rights_super_users_only;

		// First, set the video link's html
		$realtime_webservice_custom_text_dialog =
			'<div style="text-align:right;">' .
			'<i class="fas fa-film"></i> ' .
			'<a href="javascript:;" style="text-decoration:underline;" onclick="popupvid(\'ddp01.swf\',\'' . js_escape($lang['ws_285']) . '\')">'. $lang['ws_284'] .'</a>' .
			'</div>';

		$user_rights_super_users_only = $fhir_user_rights_super_users_only;
		// Set text (if custom text is provided, then display it instead of stock text)
		if (trim($fhir_custom_text) != '') {
			$realtime_webservice_custom_text_dialog .= '<div id="ddp_info_custom_text">'. nl2br(filter_tags($fhir_custom_text)) .'</div>';
		} else {
			$realtime_webservice_custom_text_dialog .= '<div id="ddp_info_custom_text">'. $lang['ws_268'] .'</div>';
		}
		$realtime_webservice_custom_text_dialog .= '<div style="font-weight:bold;font-size:110%;">'. $lang['ws_211'] .'</div>'. $lang['ws_212'];
		$realtime_webservice_custom_text_dialog .=
			'<br><br>' .
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_248'] .'</div>'.
			$lang['ws_249'] . '<br><br>';

		$demography_mc_mapping_html = '';
		foreach (self::$demography_mc_mapping as $field => $enum) {
			$enum = trim(str_replace(' | ', '<br>', $enum));
			$demography_mc_mapping_html .= '<pre style="font-size:11px;overflow:auto;max-height:150px;"><u><b>' . $field . '</b></u><br>' . $enum . '</pre>';
		}

		// enabling the feature
		$realtime_webservice_custom_text_dialog .=
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_42'] .'</div>'.
			($user_rights_super_users_only ? $lang['ws_281'] : $lang['ws_282'] . ' ' . '<b>'. $lang['ws_58'] .'</b>') . ' ' .
			$lang['ws_283'] . '<br><br>';

		// the field mapping process
		$realtime_webservice_custom_text_dialog .=
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_44'] .'</div>'.
			$lang['ws_45'] . '<br><br>';
		
		// demographics fields info
		$realtime_webservice_custom_text_dialog .=
			'<div style="">'. $lang['ws_258'] . '<br><br>' . $demography_mc_mapping_html . '</div>';
		
		// clinical notes
		$realtime_webservice_custom_text_dialog .=
		'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_340'] .'</div>'.
		'<p>'.$lang['ws_341'] . '</p>';
		$realtime_webservice_custom_text_dialog .= '<p>'.$lang['ws_346'].'</p>';
		$realtime_webservice_custom_text_dialog .= '<p>'.$lang['ws_342'] . '</p>';
		$realtime_webservice_custom_text_dialog .= '<ul>'.
			'<li>'.$lang['ws_343'].'</li>'.
			'<li>'.$lang['ws_344'].'</li>'.
			'<li>'.$lang['ws_345'].'</li>'.
		'</ul>';

		// preview fields
		$realtime_webservice_custom_text_dialog .= '<div style="font-weight:bold;font-size:110%;">'. $lang['ws_46'] .'</div>'.
			$lang['ws_47'] . '<br><br>';
		
		// adjudication process
		$realtime_webservice_custom_text_dialog .=
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_48'] .'</div>'.
			$lang['ws_49'] . ' ' . $lang['ws_59'] . ' ' . '<b>'. $lang['ws_60'] .'</b> ' . $lang['ws_61'];

		// Return HTML
		return $realtime_webservice_custom_text_dialog;
	}

	/**
	 * explanation text for custom DDP projects
	 *
	 * @return void
	 */
	public static function getDdpExplanationText()
	{
		global $lang, $realtime_webservice_custom_text, $realtime_webservice_user_rights_super_users_only;
	
		// First, set the video link's html
		$realtime_webservice_custom_text_dialog =
			'<div style="text-align:right;">' .
			'<i class="fas fa-film"></i> ' .
			'<a href="javascript:;" style="text-decoration:underline;" onclick="popupvid(\'ddp01.swf\',\'' . js_escape($lang['ws_41']) . '\')">'. $lang['ws_41'] .'</a>' .
			'</div>';
	
		$user_rights_super_users_only = $realtime_webservice_user_rights_super_users_only;
		// Set text (if custom text is provided, then display it instead of stock text)
		if (trim($realtime_webservice_custom_text) != '') {
			$realtime_webservice_custom_text_dialog .= '<div id="ddp_info_custom_text">'. nl2br(filter_tags($realtime_webservice_custom_text)) .'</div>';
		} else {
			$realtime_webservice_custom_text_dialog .= '<div id="ddp_info_custom_text">'. $lang['ws_50'] .'</div>';
		}
		$realtime_webservice_custom_text_dialog .= '<div style="font-weight:bold;font-size:110%;">'. $lang['ws_38'] .'</div>'.
												   $lang['ws_37'] . ' ' . $lang['ws_62'];
		$realtime_webservice_custom_text_dialog .=
			'<br><br>' .
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_39'] .'</div>'.
			$lang['ws_40'] . '<br><br>';
	
		$realtime_webservice_custom_text_dialog .=
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_42'] .'</div>'.
			($user_rights_super_users_only ? $lang['ws_56'] : $lang['ws_57'] . ' ' . '<b>'. $lang['ws_58'] .'</b>') . ' ' .
			$lang['ws_43'] . '<br><br>' .
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_44'] .'</div>'.
			$lang['ws_45'] . '<br><br>' .
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_46'] .'</div>'.
			$lang['ws_47'] . '<br><br>' .
			'<div style="font-weight:bold;font-size:110%;">'. $lang['ws_48'] .'</div>'.
			$lang['ws_49'] . ' ' . $lang['ws_59'] . ' ' . '<b>'. $lang['ws_60'] .'</b> ' . $lang['ws_61'];
	
		// Return HTML
		return $realtime_webservice_custom_text_dialog;
	}



	/**
	 * get preview data for the specified record identifier
	 * @deprecated 13.10.0
	 * @param string $record_identifier_external
	 * @return array
	 */
	public function getPreviewData($record_identifier_external)
	{
		if ($record_identifier_external == '') return 'ERROR!';
		// Obtain an array of the preview fields
		if ($this->realtime_webservice_type !== self::WEBSERVICE_TYPE_FHIR) return;
		// get preview fields and make the format compatible with getFhirData
		$preview_fields = $this->getPreviewFields();
		$fields = array_map(function($field) {
			return new FhirMapping($field);
		}, $preview_fields);
		// Call EHR via SMART on FHIR methods and return array of data values returned
		$fhir_data = $this->getFhirData($record_identifier_external, $fields);
		// Any errors?
		if($fhir_data->hasErrors())
		{
			$exceptions = $fhir_data->getErrors();
			$errors = array_map(function($exception) {
				$message = $exception->getMessage();
				return $message;
			}, $exceptions);
			$message = implode("\n", $errors);
			throw new Exception($message, 400);
		}else {
			return $fhir_data->getData();
			// $data is an array, but we need only 1 result
		}
	}

	/**
	 * OBTAIN DATA FOR THE PREVIEW FIELDS AND DISPLAY IT
	 */
	public function displayPreviewData($record_identifier_external)
	{
		global $lang, $realtime_webservice_url_data;

		if ($record_identifier_external == '') return 'ERROR!';
		
		$errorsHTML = "";

		// Obtain an array of the preview fields
		$preview_fields = $this->getPreviewFields();

		// Loop through fields to put in necessary array format for sending to data web service
		$field_info = [];
		foreach ($preview_fields as $this_preview_field) {
			$field_info[] = new FhirMapping($this_preview_field);
		}

		## CALL DATA WEB SERVICE// If using built-in FHIR service, call EHR via SMART on FHIR methods
		if ($this->realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR) {
			// Call EHR via SMART on FHIR methods and return array of data values returned
			$this->fhirData = $fhir_data = $this->getFhirData($record_identifier_external, $field_info);
			$response_data_array = $fhir_data->getData();
			// Any errors?
			if($fhir_data->hasErrors())
			{
				$errors = $fhir_data->getErrors();

				$errorsHTML = "<div class='red' style='margin:10px 0;'><b><i class='fas fa-exclamation-triangle'></i> {$lang['global_03']}{$lang['colon']}</b> {$lang['ws_246']}<ul style='margin:0;'>";
				foreach ($errors as $error) {
					$errorMessage = $error->getMessage();
					$errorsHTML .= '<li>'.$errorMessage.'</li>';
				}
				$errorsHTML .= "</ul></div>";
			}
		}	
		// Call the custom data web service URL as POST request
		else {
			// Set params to send in POST request (all JSON-encoded in single parameter 'body')
			// $params = array('user'=>USERID, 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL,
							// 'body' => json_encode(array('id'=>$record_identifier_external, 'fields'=>$field_info))
							// );
			$params = array('user'=>(defined('USERID') ? USERID : ''), 'project_id'=>$this->project_id, 'redcap_url'=>APP_PATH_WEBROOT_FULL,
							'id'=>$record_identifier_external, 'fields'=>$field_info);
			// Call the URL as POST request
			$response_json = http_post($realtime_webservice_url_data, $params, 30, 'application/json');
			// Decode json into array
			$response_data_array = json_decode($response_json, true);
		}

		// Display an error if the web service can't be reached or if the response is not JSON encoded
		if ((isset($response_json) && !$response_json) || !is_array($response_data_array)) {
			$error_msg = $lang['ws_137']."<br><br>";
			if ($response_json !== false && !is_array($response_data_array)) {
				$error_msg .=  $lang['ws_138']."<div style='color:#C00000;margin-top:10px;'>$response_json</div>";
			} elseif ($response_json === false) {
				$error_msg .= $lang['ws_139']." $realtime_webservice_url_data.";
			}
			exit($error_msg);
		}

		// Convert response array of data from web service into other other
		$preview_fields_data = array();
		foreach ($preview_fields as $this_preview_field) {
			// Seed with blank value first
			$preview_fields_data[$this_preview_field] = RCView::span(array('style'=>'font-weight:normal;'),
															"<i>{$lang['ws_184']}</i>"
														);
			// Find this preview field in the response array returned from web service
			foreach ($response_data_array as $attr) {
				if ($attr['field'] == $this_preview_field) {
					$preview_fields_data[$this_preview_field] = $attr['value'];
				}
			}
		}

		## Build HTML table for displaying the preview field data
		// Row for source ID value
		$rows = RCView::tr(array(),
					RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
						self::getMappedIdFieldExternal($this->project_id) . $lang['colon']
					) .
					RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
						$record_identifier_external .
						// If no data returned, then display warning icon
						(!empty($response_data_array) ? '' :
							RCView::img(array('src'=>'exclamation_red.png', 'style'=>'margin-left:7px;'))
						)
					)
				);
		// Display row for each preview field
		foreach ($preview_fields_data as $this_preview_field=>$this_preview_field_value) {
			$rows .= 	RCView::tr(array(),
							RCView::td(array('valign'=>'top', 'style'=>'text-align:right;font-size:14px;'),
								$this_preview_field . $lang['colon']
							) .
							RCView::td(array('valign'=>'top', 'style'=>'font-weight:bold;font-size:14px;color:#C00000;'),
								$this_preview_field_value
							)
						);
		}
		// Render table
		$html = RCView::div(array('style'=>'font-size:13px;font-weight:bold;margin-bottom:5px;'),
					$lang['ws_185'] . " \"$record_identifier_external\"{$lang['questionmark']}"
				) .
				RCView::table(array('id'=>'rtws_idfield_new_record_preview_table', 'style'=>'table-layout:fixed;margin-left:20px;'), $rows) . 
				$errorsHTML;

		// EXTENDED LOGGING: Log all values that were displayed in the popup to user
		if (!empty($response_data_array)) {
			// Get ui_id of user
			$userInfo = User::getUserInfo(USERID);
			// Now log all these md_ids in mapping_logging table
			$sql = "insert into redcap_ddp_log_view (time_viewed, user_id, project_id, source_id)
					values ('".NOW."', ".checkNull($userInfo['ui_id']).", ".$this->project_id.", '".db_escape($record_identifier_external)."')";
			if (db_query($sql)) {
				// Get ml_id from insert
				$ml_id = db_insert_id();
				// Now add each data point to mapping_logging_data table
				foreach ($response_data_array as $attr) {
					$sql = "insert into redcap_ddp_log_view_data (ml_id, source_field, source_timestamp)
							values ($ml_id, '".db_escape($attr['field'])."', ".checkNull($attr['timestamp']).")";
					db_query($sql);
				}
			}
		}

		// Return html
		return $html;
	}


	/**
	 * PURGE THE DATA CACHE OF SOURCE SYSTEM DATA (ONLY IF PROJECT IS ARCHIVED/INACTIVE)
	 * Default will purge all records in project, but if parameter $record is provided, it will purge only that record.
	 */
	public function purgeDataCache($record=null)
	{
		global $status;
		// If project is not archive/inactive status, then return false (but not if doing this for single record)
		if ($status <= 1 && $record === null) return false;
		// Remove all records in mapping_records table
		$sql = "delete from redcap_ddp_records where project_id = " . $this->project_id;
		if ($record !== null) {
			$sql .= " and record = '".db_escape($record)."'";
		}
		if (!db_query($sql)) return false;
		// Log this action if purging ALL records
		if ($record === null) {
			Logging::logEvent($sql, "redcap_ddp_records", "MANAGE", $this->project_id, "project_id = " . $this->project_id, "Remove usused DDP data (DDP)");
		}
		// Return on success or failure
		return true;
	}


	/**
	 * RETURN CUSTOM NAME OF EXTERNAL SOURCE SYSTEM, ELSE RETURN STOCK TEXT IF NOT DEFINED
	 */
	static function getSourceSystemName($fhir=null, $pid=null)
	{
		global $project_id, $realtime_webservice_source_system_custom_name, $lang, $realtime_webservice_type;

		$pid = $pid ?? $project_id;
		// Return custom EHR name if using DDP on FHIR
		if ($fhir === true) {
			$fhirSystem = FhirSystem::fromProjectId($pid);
			if(!$fhirSystem) return 'EHR';
			return $fhirSystem->getEhrName();
		// Return "EHR" if using DDP on FHIR
		} elseif (defined("PROJECT_ID") && $realtime_webservice_type == self::WEBSERVICE_TYPE_FHIR && $fhir !== false) {
			$fhirSystem = FhirSystem::fromProjectId(PROJECT_ID);
			if(!$fhirSystem) return 'EHR';
			$ehrName = $fhirSystem->getEhrName();
			return (trim($ehrName) == '' ? "EHR" : $ehrName);
		// If custom name not defined
		} elseif (trim($realtime_webservice_source_system_custom_name) == '') {
			return $lang['ws_52'];
		// Return custom name
		} else {
			return $realtime_webservice_source_system_custom_name;
		}
	}


	/**
	 * Re-encrypt all the cached DDP data values in batches
	 */
	public static function reencryptCachedData()
	{
		// Set batch amount
		$limit_per_batch = 10000;
		// Find any values that have not been converted
		$sql = "select md_id, source_value 
				from redcap_ddp_records_data where source_value2 is null 
				order by md_id limit $limit_per_batch";
		$q = db_query($sql);
		// Keep tally of number of values that we re-encrypt
		$num_values_encrypted = db_num_rows($q);
		// Loop through values if there are some
		if ($num_values_encrypted > 0) {
			// If OpenSSL is not enabled, then keep returning "-1" so that the cron stays enabled until OpenSSL is finally installed
			if (!openssl_loaded()) return -1;
			// Loop through values
			while ($row = db_fetch_assoc($q))
			{
				// Decrypt source_value using Mcrypt
				$decrypted_value = decrypt($row['source_value'], self::DDP_ENCRYPTION_KEY, true);
				// Re-encrypt the value
				$reencrypted_value = encrypt($decrypted_value, self::DDP_ENCRYPTION_KEY);
				// Add value back to table
				$sql = "update redcap_ddp_records_data set source_value2 = '".db_escape($reencrypted_value)."' 
						where md_id = " . $row['md_id'];
				db_query($sql);
			}
		}
		// Return the number of values that were encrypted
		return $num_values_encrypted;
	}


    public static function getDashboardURL($project_id) {
        return APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION . "/DynamicDataPull/data_fetching_queue_dashboard?pid=$project_id";
    }

}
