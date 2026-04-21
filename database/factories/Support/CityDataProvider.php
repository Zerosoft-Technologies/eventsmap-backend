<?php

namespace Database\Factories\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CityDataProvider
{
    /** @var list<array{0: float, 1: float}> Coordinates assigned during the current seeding run (and optionally preloaded from DB). */
    private static array $usedCoordinates = [];

    /** Two points must differ by at least this in lat OR lng (~33m) to count as distinct. */
    private const MIN_SEPARATION = 0.0003;

    /**
     * @var array<int, array{
     *   region: string,
     *   city: string,
     *   country: string,
     *   country_code: string,
     *   lat: float,
     *   lng: float,
     *   timezone: string,
     *   streets: list<string>
     * }>
     */
    private const CITIES = [
        // Europe
        ['region' => 'europe', 'city' => 'Amsterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'lat' => 52.3676, 'lng' => 4.9041, 'timezone' => 'Europe/Amsterdam', 'streets' => ['Damrak', 'Kalverstraat', 'Leidseplein', 'Rembrandtplein', 'Vondelpark', 'Jordaan', 'De Pijp', 'Museumplein']],
        ['region' => 'europe', 'city' => 'London', 'country' => 'United Kingdom', 'country_code' => 'GB', 'lat' => 51.5074, 'lng' => -0.1278, 'timezone' => 'Europe/London', 'streets' => ['Oxford Street', 'Regent Street', 'Covent Garden', 'Shoreditch', 'Camden', 'Brixton', 'Soho', 'Canary Wharf']],
        ['region' => 'europe', 'city' => 'Paris', 'country' => 'France', 'country_code' => 'FR', 'lat' => 48.8566, 'lng' => 2.3522, 'timezone' => 'Europe/Paris', 'streets' => ['Champs-Elysees', 'Montmartre', 'Le Marais', 'Saint-Germain', 'Bastille', 'Pigalle', 'Oberkampf', 'Belleville']],
        ['region' => 'europe', 'city' => 'Berlin', 'country' => 'Germany', 'country_code' => 'DE', 'lat' => 52.5200, 'lng' => 13.4050, 'timezone' => 'Europe/Berlin', 'streets' => ['Unter den Linden', 'Friedrichstrasse', 'Kurfurstendamm', 'Alexanderplatz', 'Prenzlauer Berg', 'Kreuzberg', 'Neukolln', 'Potsdamer Platz']],
        ['region' => 'europe', 'city' => 'Barcelona', 'country' => 'Spain', 'country_code' => 'ES', 'lat' => 41.3851, 'lng' => 2.1734, 'timezone' => 'Europe/Madrid', 'streets' => ['La Rambla', 'Passeig de Gracia', 'El Born', 'Gracia', 'Barri Gotic', 'Eixample', 'Poblenou', 'Montjuic']],
        ['region' => 'europe', 'city' => 'Madrid', 'country' => 'Spain', 'country_code' => 'ES', 'lat' => 40.4168, 'lng' => -3.7038, 'timezone' => 'Europe/Madrid', 'streets' => ['Gran Via', 'Puerta del Sol', 'Malasana', 'Chueca', 'La Latina', 'Salamanca', 'Lavapies', 'Retiro']],
        ['region' => 'europe', 'city' => 'Rome', 'country' => 'Italy', 'country_code' => 'IT', 'lat' => 41.9028, 'lng' => 12.4964, 'timezone' => 'Europe/Rome', 'streets' => ['Via del Corso', 'Trastevere', 'Piazza Navona', 'Via Veneto', 'Testaccio', 'Monti', 'Campo de Fiori', 'Prati']],
        ['region' => 'europe', 'city' => 'Milan', 'country' => 'Italy', 'country_code' => 'IT', 'lat' => 45.4642, 'lng' => 9.1900, 'timezone' => 'Europe/Rome', 'streets' => ['Via Montenapoleone', 'Navigli', 'Brera', 'Porta Nuova', 'Corso Buenos Aires', 'Isola', 'Duomo', 'Tortona']],
        ['region' => 'europe', 'city' => 'Vienna', 'country' => 'Austria', 'country_code' => 'AT', 'lat' => 48.2082, 'lng' => 16.3738, 'timezone' => 'Europe/Vienna', 'streets' => ['Ringstrasse', 'Stephansplatz', 'Mariahilfer Strasse', 'Praterstrasse', 'Leopoldstadt', 'Neubau', 'Landstrasse', 'Favoriten']],
        ['region' => 'europe', 'city' => 'Prague', 'country' => 'Czech Republic', 'country_code' => 'CZ', 'lat' => 50.0755, 'lng' => 14.4378, 'timezone' => 'Europe/Prague', 'streets' => ['Wenceslas Square', 'Old Town Square', 'Mala Strana', 'Vinohrady', 'Zizkov', 'Karlin', 'Narodni', 'Letna']],
        ['region' => 'europe', 'city' => 'Budapest', 'country' => 'Hungary', 'country_code' => 'HU', 'lat' => 47.4979, 'lng' => 19.0402, 'timezone' => 'Europe/Budapest', 'streets' => ['Andrassy ut', 'Vaci utca', 'Buda Castle', 'District VII', 'Margit Korut', 'Gellert Hill', 'Corvin', 'Jozsefvaros']],
        ['region' => 'europe', 'city' => 'Warsaw', 'country' => 'Poland', 'country_code' => 'PL', 'lat' => 52.2297, 'lng' => 21.0122, 'timezone' => 'Europe/Warsaw', 'streets' => ['Nowy Swiat', 'Krakowskie Przedmiescie', 'Praga', 'Mokotow', 'Srodmiescie', 'Wola', 'Zoliborz', 'Plac Zbawiciela']],
        ['region' => 'europe', 'city' => 'Brussels', 'country' => 'Belgium', 'country_code' => 'BE', 'lat' => 50.8503, 'lng' => 4.3517, 'timezone' => 'Europe/Brussels', 'streets' => ['Grand Place', 'Avenue Louise', 'Ixelles', 'Saint-Gilles', 'Schaerbeek', 'Rue Neuve', 'Etterbeek', 'Place Jourdan']],
        ['region' => 'europe', 'city' => 'Lisbon', 'country' => 'Portugal', 'country_code' => 'PT', 'lat' => 38.7169, 'lng' => -9.1399, 'timezone' => 'Europe/Lisbon', 'streets' => ['Avenida da Liberdade', 'Bairro Alto', 'Alfama', 'Chiado', 'Belem', 'Cais do Sodre', 'Parque das Nacoes', 'Principe Real']],
        ['region' => 'europe', 'city' => 'Stockholm', 'country' => 'Sweden', 'country_code' => 'SE', 'lat' => 59.3293, 'lng' => 18.0686, 'timezone' => 'Europe/Stockholm', 'streets' => ['Gamla Stan', 'Drottninggatan', 'Sodermalm', 'Ostermalm', 'Kungsholmen', 'Norrmalm', 'Vasastan', 'Stureplan']],
        ['region' => 'europe', 'city' => 'Copenhagen', 'country' => 'Denmark', 'country_code' => 'DK', 'lat' => 55.6761, 'lng' => 12.5683, 'timezone' => 'Europe/Copenhagen', 'streets' => ['Nyhavn', 'Stroget', 'Vesterbro', 'Norrebro', 'Frederiksberg', 'Amager', 'Christianshavn', 'Kongens Nytorv']],
        ['region' => 'europe', 'city' => 'Oslo', 'country' => 'Norway', 'country_code' => 'NO', 'lat' => 59.9139, 'lng' => 10.7522, 'timezone' => 'Europe/Oslo', 'streets' => ['Karl Johans gate', 'Aker Brygge', 'Grunerlokka', 'Majorstuen', 'Frogner', 'Tjuvholmen', 'Bjorvika', 'St Hanshaugen']],
        ['region' => 'europe', 'city' => 'Helsinki', 'country' => 'Finland', 'country_code' => 'FI', 'lat' => 60.1699, 'lng' => 24.9384, 'timezone' => 'Europe/Helsinki', 'streets' => ['Mannerheimintie', 'Esplanadi', 'Kamppi', 'Kallio', 'Punavuori', 'Katajanokka', 'Ruoholahti', 'Pasila']],
        ['region' => 'europe', 'city' => 'Athens', 'country' => 'Greece', 'country_code' => 'GR', 'lat' => 37.9838, 'lng' => 23.7275, 'timezone' => 'Europe/Athens', 'streets' => ['Syntagma', 'Monastiraki', 'Plaka', 'Kolonaki', 'Psiri', 'Exarchia', 'Piraeus', 'Koukaki']],
        ['region' => 'europe', 'city' => 'Dublin', 'country' => 'Ireland', 'country_code' => 'IE', 'lat' => 53.3498, 'lng' => -6.2603, 'timezone' => 'Europe/Dublin', 'streets' => ['OConnell Street', 'Grafton Street', 'Temple Bar', 'Docklands', 'Ranelagh', 'Smithfield', 'Stoneybatter', 'Portobello']],
        ['region' => 'europe', 'city' => 'Zurich', 'country' => 'Switzerland', 'country_code' => 'CH', 'lat' => 47.3769, 'lng' => 8.5417, 'timezone' => 'Europe/Zurich', 'streets' => ['Bahnhofstrasse', 'Langstrasse', 'Niederdorf', 'Seefeld', 'Oerlikon', 'Wiedikon', 'Enge', 'Altstadt']],
        ['region' => 'europe', 'city' => 'Geneva', 'country' => 'Switzerland', 'country_code' => 'CH', 'lat' => 46.2044, 'lng' => 6.1432, 'timezone' => 'Europe/Zurich', 'streets' => ['Rue du Rhone', 'Plainpalais', 'Carouge', 'Eaux-Vives', 'Paquis', 'Jonction', 'Rive', 'Cornavin']],
        ['region' => 'europe', 'city' => 'Munich', 'country' => 'Germany', 'country_code' => 'DE', 'lat' => 48.1351, 'lng' => 11.5820, 'timezone' => 'Europe/Berlin', 'streets' => ['Marienplatz', 'Leopoldstrasse', 'Schwabing', 'Glockenbach', 'Maxvorstadt', 'Sendlinger Tor', 'Isartor', 'Haidhausen']],
        ['region' => 'europe', 'city' => 'Hamburg', 'country' => 'Germany', 'country_code' => 'DE', 'lat' => 53.5753, 'lng' => 10.0153, 'timezone' => 'Europe/Berlin', 'streets' => ['Reeperbahn', 'Jungfernstieg', 'Schanzenviertel', 'Altona', 'HafenCity', 'St Pauli', 'Eppendorf', 'Sternschanze']],
        ['region' => 'europe', 'city' => 'Frankfurt', 'country' => 'Germany', 'country_code' => 'DE', 'lat' => 50.1109, 'lng' => 8.6821, 'timezone' => 'Europe/Berlin', 'streets' => ['Zeil', 'Sachsenhausen', 'Bahnhofsviertel', 'Bockenheim', 'Bornheim', 'Westend', 'Altstadt', 'Ostend']],
        ['region' => 'europe', 'city' => 'Rotterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'lat' => 51.9244, 'lng' => 4.4777, 'timezone' => 'Europe/Amsterdam', 'streets' => ['Coolsingel', 'Witte de Withstraat', 'Kralingen', 'Kop van Zuid', 'Delfshaven', 'Blaak', 'Oude Haven', 'Meent']],
        ['region' => 'europe', 'city' => 'Lyon', 'country' => 'France', 'country_code' => 'FR', 'lat' => 45.7640, 'lng' => 4.8357, 'timezone' => 'Europe/Paris', 'streets' => ['Presquile', 'Vieux Lyon', 'Part-Dieu', 'Croix-Rousse', 'Confluence', 'Guillotiere', 'Brotteaux', 'Bellecour']],
        ['region' => 'europe', 'city' => 'Nice', 'country' => 'France', 'country_code' => 'FR', 'lat' => 43.7102, 'lng' => 7.2620, 'timezone' => 'Europe/Paris', 'streets' => ['Promenade des Anglais', 'Old Town', 'Cimiez', 'Liberation', 'Port Lympia', 'Jean Medecin', 'Garibaldi', 'Carras']],
        ['region' => 'europe', 'city' => 'Marseille', 'country' => 'France', 'country_code' => 'FR', 'lat' => 43.2965, 'lng' => 5.3698, 'timezone' => 'Europe/Paris', 'streets' => ['La Canebiere', 'Le Panier', 'Vieux Port', 'Cours Julien', 'Noailles', 'Prado', 'Endoume', 'Castellane']],
        ['region' => 'europe', 'city' => 'Edinburgh', 'country' => 'Scotland', 'country_code' => 'GB', 'lat' => 55.9533, 'lng' => -3.1883, 'timezone' => 'Europe/London', 'streets' => ['Royal Mile', 'Princes Street', 'Leith Walk', 'Grassmarket', 'Stockbridge', 'New Town', 'Cowgate', 'Bruntsfield']],
        ['region' => 'europe', 'city' => 'Manchester', 'country' => 'England', 'country_code' => 'GB', 'lat' => 53.4808, 'lng' => -2.2426, 'timezone' => 'Europe/London', 'streets' => ['Deansgate', 'Northern Quarter', 'Ancoats', 'Spinningfields', 'Oxford Road', 'Salford Quays', 'Castlefield', 'Fallowfield']],
        ['region' => 'europe', 'city' => 'Birmingham', 'country' => 'England', 'country_code' => 'GB', 'lat' => 52.4862, 'lng' => -1.8904, 'timezone' => 'Europe/London', 'streets' => ['New Street', 'Digbeth', 'Jewellery Quarter', 'Brindleyplace', 'Broad Street', 'Harborne', 'Edgbaston', 'Moseley']],
        ['region' => 'europe', 'city' => 'Valencia', 'country' => 'Spain', 'country_code' => 'ES', 'lat' => 39.4699, 'lng' => -0.3763, 'timezone' => 'Europe/Madrid', 'streets' => ['Ciutat Vella', 'Ruzafa', 'El Carmen', 'Cabanyal', 'Gran Via', 'Benimaclet', 'Aragon', 'Avenida del Puerto']],
        ['region' => 'europe', 'city' => 'Seville', 'country' => 'Spain', 'country_code' => 'ES', 'lat' => 37.3891, 'lng' => -5.9845, 'timezone' => 'Europe/Madrid', 'streets' => ['Triana', 'Santa Cruz', 'Alameda', 'Nervion', 'Macarena', 'Arenal', 'Calle Sierpes', 'Los Remedios']],
        ['region' => 'europe', 'city' => 'Porto', 'country' => 'Portugal', 'country_code' => 'PT', 'lat' => 41.1579, 'lng' => -8.6291, 'timezone' => 'Europe/Lisbon', 'streets' => ['Ribeira', 'Aliados', 'Cedofeita', 'Boavista', 'Foz do Douro', 'Miragaia', 'Campanha', 'Rua de Santa Catarina']],

        // United States
        ['region' => 'us', 'city' => 'New York City', 'country' => 'USA', 'country_code' => 'US', 'lat' => 40.7128, 'lng' => -74.0060, 'timezone' => 'America/New_York', 'streets' => ['Times Square', 'Brooklyn', 'SoHo', 'Harlem', 'Lower East Side', 'Midtown', 'Greenwich Village', 'Tribeca']],
        ['region' => 'us', 'city' => 'Los Angeles', 'country' => 'USA', 'country_code' => 'US', 'lat' => 34.0522, 'lng' => -118.2437, 'timezone' => 'America/Los_Angeles', 'streets' => ['Hollywood Blvd', 'Sunset Blvd', 'Downtown LA', 'Venice', 'Santa Monica', 'Silver Lake', 'Koreatown', 'Beverly Grove']],
        ['region' => 'us', 'city' => 'Chicago', 'country' => 'USA', 'country_code' => 'US', 'lat' => 41.8781, 'lng' => -87.6298, 'timezone' => 'America/Chicago', 'streets' => ['Michigan Avenue', 'Wicker Park', 'Logan Square', 'River North', 'The Loop', 'Pilsen', 'Gold Coast', 'Lincoln Park']],
        ['region' => 'us', 'city' => 'Houston', 'country' => 'USA', 'country_code' => 'US', 'lat' => 29.7604, 'lng' => -95.3698, 'timezone' => 'America/Chicago', 'streets' => ['Downtown', 'Midtown', 'Montrose', 'The Heights', 'EaDo', 'River Oaks', 'Museum District', 'Galleria']],
        ['region' => 'us', 'city' => 'Miami', 'country' => 'USA', 'country_code' => 'US', 'lat' => 25.7617, 'lng' => -80.1918, 'timezone' => 'America/New_York', 'streets' => ['South Beach', 'Wynwood', 'Brickell', 'Little Havana', 'Design District', 'Coconut Grove', 'Downtown', 'Edgewater']],
        ['region' => 'us', 'city' => 'San Francisco', 'country' => 'USA', 'country_code' => 'US', 'lat' => 37.7749, 'lng' => -122.4194, 'timezone' => 'America/Los_Angeles', 'streets' => ['Market Street', 'Mission District', 'SoMa', 'Castro', 'Nob Hill', 'Haight-Ashbury', 'Marina', 'Chinatown']],
        ['region' => 'us', 'city' => 'Las Vegas', 'country' => 'USA', 'country_code' => 'US', 'lat' => 36.1699, 'lng' => -115.1398, 'timezone' => 'America/Los_Angeles', 'streets' => ['The Strip', 'Fremont Street', 'Arts District', 'Summerlin', 'Downtown', 'Paradise Road', 'Chinatown Plaza', 'Spring Valley']],
        ['region' => 'us', 'city' => 'Seattle', 'country' => 'USA', 'country_code' => 'US', 'lat' => 47.6062, 'lng' => -122.3321, 'timezone' => 'America/Los_Angeles', 'streets' => ['Pike Place', 'Capitol Hill', 'Belltown', 'Ballard', 'Fremont', 'South Lake Union', 'Queen Anne', 'University District']],
        ['region' => 'us', 'city' => 'Boston', 'country' => 'USA', 'country_code' => 'US', 'lat' => 42.3601, 'lng' => -71.0589, 'timezone' => 'America/New_York', 'streets' => ['Back Bay', 'Beacon Hill', 'North End', 'South Boston', 'Fenway', 'Seaport', 'Cambridge Street', 'Allston']],
        ['region' => 'us', 'city' => 'Washington DC', 'country' => 'USA', 'country_code' => 'US', 'lat' => 38.9072, 'lng' => -77.0369, 'timezone' => 'America/New_York', 'streets' => ['Georgetown', 'Dupont Circle', 'U Street', 'Capitol Hill', 'Adams Morgan', 'Navy Yard', 'Shaw', 'H Street']],
        ['region' => 'us', 'city' => 'Austin', 'country' => 'USA', 'country_code' => 'US', 'lat' => 30.2672, 'lng' => -97.7431, 'timezone' => 'America/Chicago', 'streets' => ['6th Street', 'South Congress', 'Rainey Street', 'East Austin', 'Zilker', 'Domain', 'Downtown', 'Hyde Park']],
        ['region' => 'us', 'city' => 'Nashville', 'country' => 'USA', 'country_code' => 'US', 'lat' => 36.1627, 'lng' => -86.7816, 'timezone' => 'America/Chicago', 'streets' => ['Broadway', 'The Gulch', 'East Nashville', '12 South', 'Music Row', 'Germantown', 'SoBro', 'Five Points']],
        ['region' => 'us', 'city' => 'New Orleans', 'country' => 'USA', 'country_code' => 'US', 'lat' => 29.9511, 'lng' => -90.0715, 'timezone' => 'America/Chicago', 'streets' => ['Bourbon Street', 'Frenchmen Street', 'Garden District', 'Magazine Street', 'Marigny', 'Bywater', 'Canal Street', 'CBD']],
        ['region' => 'us', 'city' => 'Atlanta', 'country' => 'USA', 'country_code' => 'US', 'lat' => 33.7490, 'lng' => -84.3880, 'timezone' => 'America/New_York', 'streets' => ['Midtown', 'Buckhead', 'Downtown', 'Old Fourth Ward', 'Virginia-Highland', 'Little Five Points', 'West Midtown', 'Decatur']],
        ['region' => 'us', 'city' => 'Denver', 'country' => 'USA', 'country_code' => 'US', 'lat' => 39.7392, 'lng' => -104.9903, 'timezone' => 'America/Denver', 'streets' => ['LoDo', 'RiNo', 'Capitol Hill', 'Cherry Creek', 'Highlands', 'South Broadway', 'Union Station', 'Five Points']],
        ['region' => 'us', 'city' => 'Portland', 'country' => 'USA', 'country_code' => 'US', 'lat' => 45.5051, 'lng' => -122.6750, 'timezone' => 'America/Los_Angeles', 'streets' => ['Pearl District', 'Alberta Arts', 'Hawthorne', 'Downtown', 'NW 23rd', 'Mississippi Avenue', 'Sellwood', 'St Johns']],
        ['region' => 'us', 'city' => 'Minneapolis', 'country' => 'USA', 'country_code' => 'US', 'lat' => 44.9778, 'lng' => -93.2650, 'timezone' => 'America/Chicago', 'streets' => ['Nicollet Mall', 'North Loop', 'Uptown', 'Dinkytown', 'Northeast Arts', 'Lyn-Lake', 'Downtown East', 'Longfellow']],
        ['region' => 'us', 'city' => 'Philadelphia', 'country' => 'USA', 'country_code' => 'US', 'lat' => 39.9526, 'lng' => -75.1652, 'timezone' => 'America/New_York', 'streets' => ['Center City', 'Old City', 'Fishtown', 'Rittenhouse', 'University City', 'Northern Liberties', 'South Street', 'Fairmount']],
        ['region' => 'us', 'city' => 'San Diego', 'country' => 'USA', 'country_code' => 'US', 'lat' => 32.7157, 'lng' => -117.1611, 'timezone' => 'America/Los_Angeles', 'streets' => ['Gaslamp Quarter', 'Little Italy', 'La Jolla', 'Pacific Beach', 'North Park', 'Hillcrest', 'Mission Valley', 'Old Town']],
        ['region' => 'us', 'city' => 'Phoenix', 'country' => 'USA', 'country_code' => 'US', 'lat' => 33.4484, 'lng' => -112.0740, 'timezone' => 'America/Phoenix', 'streets' => ['Downtown Phoenix', 'Roosevelt Row', 'Arcadia', 'Tempe', 'Scottsdale Road', 'Camelback East', 'Biltmore', 'Melrose']],

        // India
        ['region' => 'india', 'city' => 'Mumbai', 'country' => 'India', 'country_code' => 'IN', 'lat' => 19.0760, 'lng' => 72.8777, 'timezone' => 'Asia/Kolkata', 'streets' => ['Bandra', 'Colaba', 'Juhu', 'Worli', 'Powai', 'Andheri', 'Lower Parel', 'Fort']],
        ['region' => 'india', 'city' => 'Delhi', 'country' => 'India', 'country_code' => 'IN', 'lat' => 28.6139, 'lng' => 77.2090, 'timezone' => 'Asia/Kolkata', 'streets' => ['Connaught Place', 'Hauz Khas', 'Saket', 'Karol Bagh', 'Lajpat Nagar', 'Rajouri Garden', 'Dwarka', 'Chandni Chowk']],
        ['region' => 'india', 'city' => 'Bangalore', 'country' => 'India', 'country_code' => 'IN', 'lat' => 12.9716, 'lng' => 77.5946, 'timezone' => 'Asia/Kolkata', 'streets' => ['MG Road', 'Indiranagar', 'Koramangala', 'Whitefield', 'Jayanagar', 'HSR Layout', 'Brigade Road', 'Malleshwaram']],
        ['region' => 'india', 'city' => 'Chennai', 'country' => 'India', 'country_code' => 'IN', 'lat' => 13.0827, 'lng' => 80.2707, 'timezone' => 'Asia/Kolkata', 'streets' => ['T Nagar', 'Anna Nagar', 'OMR', 'Adyar', 'Mylapore', 'Nungambakkam', 'Velachery', 'Marina Beach Road']],
        ['region' => 'india', 'city' => 'Hyderabad', 'country' => 'India', 'country_code' => 'IN', 'lat' => 17.3850, 'lng' => 78.4867, 'timezone' => 'Asia/Kolkata', 'streets' => ['Banjara Hills', 'Jubilee Hills', 'Hitech City', 'Gachibowli', 'Charminar', 'Secunderabad', 'Begumpet', 'Madhapur']],
        ['region' => 'india', 'city' => 'Kolkata', 'country' => 'India', 'country_code' => 'IN', 'lat' => 22.5726, 'lng' => 88.3639, 'timezone' => 'Asia/Kolkata', 'streets' => ['Park Street', 'Salt Lake', 'New Town', 'Ballygunge', 'Howrah', 'Dum Dum', 'Gariahat', 'Esplanade']],
        ['region' => 'india', 'city' => 'Pune', 'country' => 'India', 'country_code' => 'IN', 'lat' => 18.5204, 'lng' => 73.8567, 'timezone' => 'Asia/Kolkata', 'streets' => ['Koregaon Park', 'Baner', 'Kothrud', 'Viman Nagar', 'Hinjewadi', 'FC Road', 'Shivajinagar', 'Wakad']],
        ['region' => 'india', 'city' => 'Ahmedabad', 'country' => 'India', 'country_code' => 'IN', 'lat' => 23.0225, 'lng' => 72.5714, 'timezone' => 'Asia/Kolkata', 'streets' => ['CG Road', 'Satellite', 'Navrangpura', 'Maninagar', 'Bopal', 'Prahlad Nagar', 'SG Highway', 'Ellis Bridge']],
        ['region' => 'india', 'city' => 'Jaipur', 'country' => 'India', 'country_code' => 'IN', 'lat' => 26.9124, 'lng' => 75.7873, 'timezone' => 'Asia/Kolkata', 'streets' => ['MI Road', 'C Scheme', 'Malviya Nagar', 'Vaishali Nagar', 'Johari Bazaar', 'Bapu Nagar', 'Tonk Road', 'Raja Park']],
        ['region' => 'india', 'city' => 'Goa', 'country' => 'India', 'country_code' => 'IN', 'lat' => 15.2993, 'lng' => 74.1240, 'timezone' => 'Asia/Kolkata', 'streets' => ['Panaji', 'Calangute', 'Baga', 'Anjuna', 'Candolim', 'Margao', 'Vasco da Gama', 'Mapusa']],
        ['region' => 'india', 'city' => 'Kochi', 'country' => 'India', 'country_code' => 'IN', 'lat' => 9.9312, 'lng' => 76.2673, 'timezone' => 'Asia/Kolkata', 'streets' => ['Fort Kochi', 'Marine Drive', 'MG Road', 'Edappally', 'Kakkanad', 'Kadavanthra', 'Vyttila', 'Mattancherry']],
        ['region' => 'india', 'city' => 'Chandigarh', 'country' => 'India', 'country_code' => 'IN', 'lat' => 30.7333, 'lng' => 76.7794, 'timezone' => 'Asia/Kolkata', 'streets' => ['Sector 17', 'Sector 22', 'Sector 35', 'Manimajra', 'Industrial Area', 'IT Park', 'Sector 26', 'Sector 8']],
        ['region' => 'india', 'city' => 'Surat', 'country' => 'India', 'country_code' => 'IN', 'lat' => 21.1702, 'lng' => 72.8311, 'timezone' => 'Asia/Kolkata', 'streets' => ['Ring Road', 'Athwa', 'Vesu', 'Adajan', 'Katargam', 'Udhna', 'Piplod', 'Varachha']],
        ['region' => 'india', 'city' => 'Lucknow', 'country' => 'India', 'country_code' => 'IN', 'lat' => 26.8467, 'lng' => 80.9462, 'timezone' => 'Asia/Kolkata', 'streets' => ['Hazratganj', 'Gomti Nagar', 'Aliganj', 'Aminabad', 'Indira Nagar', 'Mahanagar', 'Chowk', 'Vikas Nagar']],
        ['region' => 'india', 'city' => 'Coimbatore', 'country' => 'India', 'country_code' => 'IN', 'lat' => 11.0168, 'lng' => 76.9558, 'timezone' => 'Asia/Kolkata', 'streets' => ['RS Puram', 'Peelamedu', 'Gandhipuram', 'Saibaba Colony', 'Avinashi Road', 'Race Course', 'Singanallur', 'Ukkadam']],

        // Rest of world
        ['region' => 'rest', 'city' => 'Dubai', 'country' => 'UAE', 'country_code' => 'AE', 'lat' => 25.2048, 'lng' => 55.2708, 'timezone' => 'Asia/Dubai', 'streets' => ['Downtown Dubai', 'Dubai Marina', 'JBR', 'Business Bay', 'Deira', 'Jumeirah', 'Al Barsha', 'DIFC']],
        ['region' => 'rest', 'city' => 'Singapore', 'country' => 'Singapore', 'country_code' => 'SG', 'lat' => 1.3521, 'lng' => 103.8198, 'timezone' => 'Asia/Singapore', 'streets' => ['Orchard Road', 'Marina Bay', 'Bugis', 'Chinatown', 'Clarke Quay', 'Tiong Bahru', 'Sentosa', 'Little India']],
        ['region' => 'rest', 'city' => 'Tokyo', 'country' => 'Japan', 'country_code' => 'JP', 'lat' => 35.6762, 'lng' => 139.6503, 'timezone' => 'Asia/Tokyo', 'streets' => ['Shibuya', 'Shinjuku', 'Roppongi', 'Ginza', 'Asakusa', 'Akihabara', 'Harajuku', 'Ikebukuro']],
        ['region' => 'rest', 'city' => 'Sydney', 'country' => 'Australia', 'country_code' => 'AU', 'lat' => -33.8688, 'lng' => 151.2093, 'timezone' => 'Australia/Sydney', 'streets' => ['George Street', 'Surry Hills', 'Bondi', 'Darling Harbour', 'Newtown', 'Parramatta', 'The Rocks', 'Pyrmont']],
        ['region' => 'rest', 'city' => 'Toronto', 'country' => 'Canada', 'country_code' => 'CA', 'lat' => 43.6532, 'lng' => -79.3832, 'timezone' => 'America/Toronto', 'streets' => ['Queen Street West', 'King Street', 'Yorkville', 'Distillery District', 'Kensington Market', 'The Annex', 'Liberty Village', 'Harbourfront']],
        ['region' => 'rest', 'city' => 'Sao Paulo', 'country' => 'Brazil', 'country_code' => 'BR', 'lat' => -23.5505, 'lng' => -46.6333, 'timezone' => 'America/Sao_Paulo', 'streets' => ['Avenida Paulista', 'Vila Madalena', 'Pinheiros', 'Moema', 'Ibirapuera', 'Jardins', 'Centro', 'Liberdade']],
        ['region' => 'rest', 'city' => 'Cape Town', 'country' => 'South Africa', 'country_code' => 'ZA', 'lat' => -33.9249, 'lng' => 18.4241, 'timezone' => 'Africa/Johannesburg', 'streets' => ['Long Street', 'Sea Point', 'Camps Bay', 'Woodstock', 'City Bowl', 'Green Point', 'Waterfront', 'Observatory']],
        ['region' => 'rest', 'city' => 'Bangkok', 'country' => 'Thailand', 'country_code' => 'TH', 'lat' => 13.7563, 'lng' => 100.5018, 'timezone' => 'Asia/Bangkok', 'streets' => ['Sukhumvit', 'Silom', 'Siam', 'Thonglor', 'Asok', 'Ari', 'Ratchada', 'Chatuchak']],
        ['region' => 'rest', 'city' => 'Istanbul', 'country' => 'Turkey', 'country_code' => 'TR', 'lat' => 41.0082, 'lng' => 28.9784, 'timezone' => 'Europe/Istanbul', 'streets' => ['Taksim', 'Besiktas', 'Kadikoy', 'Galata', 'Nisantasi', 'Beyoglu', 'Uskudar', 'Sultanahmet']],
        ['region' => 'rest', 'city' => 'Mexico City', 'country' => 'Mexico', 'country_code' => 'MX', 'lat' => 19.4326, 'lng' => -99.1332, 'timezone' => 'America/Mexico_City', 'streets' => ['Polanco', 'Roma Norte', 'Condesa', 'Centro Historico', 'Coyoacan', 'Reforma', 'Juarez', 'Santa Fe']],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::CITIES;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getWeightedCity(): array
    {
        $roll = mt_rand(1, 100);
        $region = match (true) {
            $roll <= 50 => 'europe',
            $roll <= 75 => 'us',
            $roll <= 90 => 'india',
            default => 'rest',
        };

        return self::randomCityInRegion($region);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getCityByName(string $cityName): array
    {
        foreach (self::CITIES as $city) {
            if (strcasecmp($city['city'], $cityName) === 0) {
                return $city;
            }
        }

        throw new InvalidArgumentException("City not found in CityDataProvider: {$cityName}");
    }

    /**
     * @param  array<string, mixed>  $city
     * @return array<string, mixed>
     */
    public static function randomizeCoordinates(array $city): array
    {
        return self::getUniqueCoordinates($city);
    }

    /**
     * Assigns a unique lat/lng near the city's center and registers it for this seed run.
     *
     * @param  array<string, mixed>  $city
     * @return array<string, mixed>
     */
    public static function getUniqueCoordinates(array $city): array
    {
        $baseLat = (float) $city['lat'];
        $baseLng = (float) $city['lng'];

        foreach ([800, 2000, 5000] as $rangeThousandths) {
            $pair = self::attemptRandomPair($baseLat, $baseLng, $rangeThousandths);
            if ($pair !== null) {
                return self::applyPair($city, $pair[0], $pair[1]);
            }
        }

        for ($k = 1; $k <= 50_000; $k++) {
            $lat = round($baseLat + ($k * self::MIN_SEPARATION * 2), 6);
            $lng = round($baseLng + ($k * self::MIN_SEPARATION * 2.37), 6);
            $lat = max(-90.0, min(90.0, $lat));
            $lng = max(-180.0, min(180.0, $lng));
            if (! self::isTooClose($lat, $lng)) {
                return self::applyPair($city, $lat, $lng);
            }
        }

        throw new \RuntimeException('CityDataProvider: could not allocate a unique coordinate pair.');
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private static function attemptRandomPair(float $baseLat, float $baseLng, int $rangeThousandths): ?array
    {
        for ($i = 0; $i < 200; $i++) {
            $lat = round($baseLat + (mt_rand(-$rangeThousandths, $rangeThousandths) / 10000), 6);
            $lng = round($baseLng + (mt_rand(-$rangeThousandths, $rangeThousandths) / 10000), 6);
            $lat = max(-90.0, min(90.0, $lat));
            $lng = max(-180.0, min(180.0, $lng));

            if (! self::isTooClose($lat, $lng)) {
                return [$lat, $lng];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $city
     * @return array<string, mixed>
     */
    private static function applyPair(array $city, float $lat, float $lng): array
    {
        self::$usedCoordinates[] = [$lat, $lng];
        $city['lat'] = $lat;
        $city['lng'] = $lng;

        return $city;
    }

    private static function isTooClose(float $lat, float $lng): bool
    {
        foreach (self::$usedCoordinates as [$usedLat, $usedLng]) {
            $latDiff = abs($lat - $usedLat);
            $lngDiff = abs($lng - $usedLng);
            if ($latDiff < self::MIN_SEPARATION && $lngDiff < self::MIN_SEPARATION) {
                return true;
            }
        }

        return false;
    }

    public static function resetRegistry(): void
    {
        self::$usedCoordinates = [];
    }

    /**
     * Preload coordinates already stored (e.g. partial reseed without migrate:fresh).
     */
    public static function loadExistingCoordinatesFromDB(): void
    {
        $tables = ['events_v2', 'organiser_v2', 'talents_v2', 'venue_v2'];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        self::$usedCoordinates[] = [
                            (float) $row->latitude,
                            (float) $row->longitude,
                        ];
                    }
                });
        }
    }

    /**
     * Vary address formatting so list views do not show identical strings for different records.
     *
     * @param  array<string, mixed>  $city
     */
    public static function randomFormattedAddress(array $city): string
    {
        $num = \fake()->numberBetween(1, 350);
        $street = \fake()->randomElement($city['streets']);
        $cityName = $city['city'];
        $country = $city['country'];

        $formats = [
            fn (): string => "{$num} {$street}, {$cityName}, {$country}",
            fn (): string => 'Unit '.\fake()->numberBetween(1, 20).", {$num} {$street}, {$cityName}, {$country}",
            fn (): string => "{$street} {$num}, {$cityName}, {$country}",
            fn (): string => \fake()->randomElement(['The ', 'Central ', 'Metro ', 'City ', 'Grand ']).$street.', '.$cityName.', '.$country,
        ];

        return \fake()->randomElement($formats)();
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomCityInRegion(string $region): array
    {
        $matches = array_values(array_filter(self::CITIES, static fn (array $city): bool => $city['region'] === $region));

        if ($matches === []) {
            return self::CITIES[array_rand(self::CITIES)];
        }

        return $matches[array_rand($matches)];
    }
}
