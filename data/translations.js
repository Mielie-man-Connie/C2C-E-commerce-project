/* Translations: UI text for multiple languages (en, af, zu, xh, nso) */
/* Include via <script src="../data/translations.js"></script> and use helpers on window */

// All UI text translations
const TRANSLATION_DATA = {
    en: {
        // Navigation & Header
        settings: 'Settings',
        myListings: 'My Listings',
        history: 'History',
        reportIssue: 'Report an Issue',
        signOut: 'Sign Out',
        
        // Account Setup Page
        welcomeToTradeSA: 'Welcome to TradeSA',
        completeYourProfile: 'Complete your profile',
        requiredFields: 'Fields marked * are required. Fill in all optional fields to become a Verified seller.',
        requiredSectionLabel: 'Required',
        optionalSectionLabel: 'Optional — fill all four to unlock Verified status',
        firstName: 'First name',
        surname: 'Surname',
        dateOfBirth: 'Date of birth',
        mobileNumber: 'Mobile number',
        saIDNumber: 'SA ID number',
        location: 'Location',
        province: 'Province',
        city: 'City',
        suburb: 'Suburb',
        profileDescription: 'Profile description',
        descriptionPlaceholder: 'Tell other traders a little about yourself…',
        uploadPhoto: 'Upload photo',
        photoHint: 'JPG · PNG · WebP | max 5 MB · optional',
        chooseProvince: 'Choose province',
        chooseCity: 'Choose city',
        chooseSuburb: 'Choose suburb',
        reportMissingLocation: 'Report missing location',
        completeAccount: 'Complete account',
        
        // Browse Page
        marketplace: 'Marketplace',
        findTreasure: 'Find your next secondhand treasure',
        filter: 'Filter items',
        filterHint: 'Narrow results by location, price, or delivery type.',
        price: 'Price Range (R)',
        location: 'Location',
        deliveryType: 'Delivery type',
        pickup: 'Pickup',
        escrow: 'Escrow',
        clearFilters: 'Clear all filters',
        noResults: 'No items found',
        noResultsHint: 'Try adjusting your filters or clearing the search.',
        search: 'Search',
        searchPlaceholder: 'Search items…',
        itemsFound: 'items found',
        itemFound: 'item found',
        any: 'Any',
        allProvinces: 'All provinces',
        allCities: 'All cities',
        allSuburbs: 'All suburbs',
        
        // ViewItem Page
        reviewsForThisSeller: 'Reviews for this seller',
        addToCart: 'Add to cart',
        buyNow: 'Buy now',
        buyViaEscrow: 'Buy via Escrow',
        contactSeller: 'Contact seller',
        leaveReview: 'Leave a Review',
        outOfStock: 'Out of Stock',
        
        // CreateReview Page
        rating: 'Rating',
        reviewTitle: 'Title',
        description: 'Description',
        max100Chars: 'Max 100 characters',
        max500Chars: 'Max 500 characters',
        submitReview: 'Submit Review',
        
        // Login & Register Page
        login: 'Login',
        register: 'Register',
        emailPlaceholder: 'Email',
        passwordPlaceholder: 'Password',
        usernamePlaceholder: 'Username',
        confirmPasswordPlaceholder: 'Confirm Password',
        dontHaveAccount: "Don't have an account?",
        alreadyHaveAccount: 'Already have an account?',
        
        // Settings Page
        profileSettings: 'Profile Settings',
        accountSettings: 'Account Settings',
        updateProfile: 'Update Profile',
        changePassword: 'Change Password',
        verifiedAccount: 'Verified account',
        notVerified: 'Not verified',
        required: 'Required',
        optionalVerified: 'Optional — complete all four for',
        status: 'status',
        firstName: 'First name',
        surname: 'Surname',
        username: 'Username',
        email: 'Email',
        emailNoChange: 'Email cannot be changed.',
        dateOfBirth: 'Date of birth',
        mobileNumber: 'Mobile number',
        mobileHint: '10-digit SA number',
        saIdNumber: 'SA ID number',
        saIdHint: '13 digits · must match date of birth',
        location: 'Location',
        province: 'Province',
        city: 'City',
        suburb: 'Suburb',
        locationHint: 'Select your saved TradeSA location. If your location is missing, report it.',
        reportMissing: 'Report missing location',
        profileDescription: 'Profile description',
        changePhoto: 'Change photo',
        photoHint: 'JPG · PNG · WebP — max 5 MB · optional',
        saveChanges: 'Save changes',
        currentPassword: 'Current password',
        newPassword: 'New password',
        confirmPassword: 'Confirm new password',
        updatePassword: 'Update password',
        
        // Common UI
        required: 'Required',
        optional: 'Optional',
        verified: 'Verified',
        save: 'Save',
        cancel: 'Cancel',
        close: 'Close',
        loading: 'Loading...',
        error: 'Error',
        success: 'Success'
    },
    
    af: {
        // Navigation & Header
        settings: 'Instellings',
        myListings: 'My Advertensies',
        history: 'Geskiedenis',
        reportIssue: 'Rapporteer \'n Probleem',
        signOut: 'Teken uit',
        
        // Account Setup Page
        welcomeToTradeSA: 'Welkom by TradeSA',
        completeYourProfile: 'Voltooi jou profiel',
        requiredFields: 'Velde wat met * gemerk is, is vereist. Vul al die opsionele velde in om \'n Geverifieerde verkoper te word.',
        requiredSectionLabel: 'Vereist',
        optionalSectionLabel: 'Opsioneel — vul al vier in om Geverifieerde status te ontgrendel',
        firstName: 'Voornaam',
        surname: 'Van',
        dateOfBirth: 'Geboortedatum',
        mobileNumber: 'Selfoon nommer',
        saIDNumber: 'SA ID-nommer',
        location: 'Ligging',
        province: 'Provinsie',
        city: 'Stad',
        suburb: 'Voorstad',
        profileDescription: 'Profielbeskrywing',
        descriptionPlaceholder: 'Vertel ander handelaars \'n bietjie oor jouself…',
        uploadPhoto: 'Laai foto op',
        photoHint: 'JPG · PNG · WebP | maks 5 MB · opsioneel',
        chooseProvince: 'Kies provinsie',
        chooseCity: 'Kies stad',
        chooseSuburb: 'Kies voorstad',
        reportMissingLocation: 'Rapporteer vermiste ligging',
        completeAccount: 'Voltooi rekening',
        
        // Browse Page
        marketplace: 'Markteplek',
        findTreasure: 'Vind jou volgende tweedehandse skat',
        filter: 'Filteritems',
        filterHint: 'Vernou resultate deur ligging, prys of lewingstype.',
        price: 'Prysreeks (R)',
        location: 'Ligging',
        deliveryType: 'Lewingstype',
        pickup: 'Opgetel',
        escrow: 'Derde party',
        clearFilters: 'Vee alle filters uit',
        noResults: 'Geen items gevind nie',
        noResultsHint: 'Probeer jou filters aanpas of wis die soektog.',
        search: 'Soek',
        searchPlaceholder: 'Soek items…',
        itemsFound: 'items gevind',
        itemFound: 'item gevind',
        any: 'Enige',
        allProvinces: 'Alle provinsies',
        allCities: 'Alle stede',
        allSuburbs: 'Alle voorstede',
        
        // ViewItem Page
        reviewsForThisSeller: 'Resensies vir hierdie verkoper',
        addToCart: 'Voeg by mandjie',
        buyNow: 'Koop nou',
        buyViaEscrow: 'Koop via Escrow',
        contactSeller: 'Kontak verkoper',
        leaveReview: 'Laat \'n resensie',
        outOfStock: 'Uitverkocht',
        
        // CreateReview Page
        rating: 'Beoordeling',
        reviewTitle: 'Titel',
        description: 'Beskrywing',
        max100Chars: 'Max 100 karakters',
        max500Chars: 'Max 500 karakters',
        submitReview: 'Lewer resensie in',
        
        // Login & Register Page
        login: 'Teken in',
        register: 'Registreer',
        emailPlaceholder: 'E-pos',
        passwordPlaceholder: 'Wagwoord',
        usernamePlaceholder: 'Gebruikersnaam',
        confirmPasswordPlaceholder: 'Bevestig wagwoord',
        dontHaveAccount: 'Het jy nie een nie?',
        alreadyHaveAccount: 'Het jy reeds een?',
        
        // Settings Page
        profileSettings: 'Profielinstellings',
        accountSettings: 'Rekeninginstellings',
        updateProfile: 'Werk profiel by',
        changePassword: 'Verander wagwoord',
        verifiedAccount: 'Geverifieerde rekening',
        notVerified: 'Nie geverifieerd',
        optionalVerified: 'Opsioneel — voltooi almal vier vir',
        status: 'status',
        firstName: 'Voornaam',
        surname: 'Van',
        username: 'Gebruikersnaam',
        email: 'E-pos',
        emailNoChange: 'E-pos kan nie verander word nie.',
        dateOfBirth: 'Geboortedatum',
        mobileNumber: 'Mobiele nommer',
        mobileHint: '10-syfernommer SA',
        saIdNumber: 'SA ID-nommer',
        saIdHint: '13 syfers · moet ooreenstem met geboortedatum',
        location: 'Ligging',
        province: 'Provinsie',
        city: 'Stad',
        suburb: 'Voorstad',
        locationHint: 'Kies jou gestuurde TradeSA-ligging. Indien jou ligging ontbreek, rapporteer dit.',
        reportMissing: 'Rapporteer ontbrekende ligging',
        profileDescription: 'Profielbeskrywing',
        changePhoto: 'Verander foto',
        photoHint: 'JPG · PNG · WebP — maks 5 MB · opsioneel',
        saveChanges: 'Stoor veranderinge',
        currentPassword: 'Huidige wagwoord',
        newPassword: 'Nuwe wagwoord',
        confirmPassword: 'Bevestig nuwe wagwoord',
        updatePassword: 'Werk wagwoord by',
        
        // Common UI
        required: 'Vereist',
        optional: 'Opsioneel',
        verified: 'Geverifieerd',
        save: 'Stoor',
        cancel: 'Kanselleer',
        close: 'Sluit',
        loading: 'Laai tans...',
        error: 'Fout',
        success: 'Sukses'
    },
    
    zu: {
        // Navigation & Header
        settings: 'Isetshenziselo',
        myListings: 'Okhombe bami',
        history: 'Umlando',
        reportIssue: 'Bika Inkinga',
        signOut: 'Phuma ngaphandle',
        
        // Account Setup Page
        welcomeToTradeSA: 'Wamkelekile ku-TradeSA',
        completeYourProfile: 'Qedela iprofayili yakho',
        requiredFields: 'Amasimu aleswe ngo-* ayidingekile. Gcwalisa zonke izinsimu ezingenaminingwane ukuze ube umthengisi Oqinisekisiwe.',
        requiredSectionLabel: 'Idingekile',
        optionalSectionLabel: 'Ingenaminingwane — gcwalisa zonke izine ukuze uvule isimo se-Verified',
        firstName: 'Igama lokuqala',
        surname: 'Isithunywa',
        dateOfBirth: 'Usuku lokuzalwa',
        mobileNumber: 'Inombolo yomnxeba omobile',
        saIDNumber: 'Inombolo ye-SA ID',
        location: 'Indawo',
        province: 'Ibanga',
        city: 'Idolobha',
        suburb: 'Isigaba',
        profileDescription: 'Incazelo yeprofayili',
        descriptionPlaceholder: 'Etshela abanye abathengisi okuthile ngawe…',
        uploadPhoto: 'Likela isithombe',
        photoHint: 'JPG · PNG · WebP | ubukhulu obukhulu buka-5 MB · ingenaminingwane',
        chooseProvince: 'Khetha ibanga',
        chooseCity: 'Khetha idolobha',
        chooseSuburb: 'Khetha isigaba',
        reportMissingLocation: 'Bika indawo eswelekile',
        completeAccount: 'Qedela i-akhawunti',
        
        // Browse Page,
        
        // Login & Register Page
        login: 'Ngena ngemvume',
        register: 'Bhalisa',
        emailPlaceholder: 'Imeyili',
        passwordPlaceholder: 'Iphasiwedi',
        usernamePlaceholder: 'Igama lomsebenzisi',
        confirmPasswordPlaceholder: 'Qinisekisa iphasiwedi',
        dontHaveAccount: 'Awunayo i-akhawunti?',
        alreadyHaveAccount: 'Usunayo i-akhawunti?',
        marketplace: 'Inkundla yokuthunga',
        findTreasure: 'Thola ikhatshana lakho elilandelayo likasebenza kabili',
        filter: 'Sihlukanise imikhiqizo',
        filterHint: 'Nciphile iziphumo nge-indawo, intengo, noma uhlobo lokuhatsha.',
        price: 'Izinga leIntengo (R)',
        location: 'Indawo',
        deliveryType: 'Uhlobo lokuhatsha',
        pickup: 'Zokuthatha',
        escrow: 'I-Escrow',
        clearFilters: 'Ziphakamisele wonke umxube',
        noResults: 'Akukho imikhiqizo etholiwe',
        noResultsHint: 'Zama ukuguqula izihlungi zakho noma gubha ucwaningo.',
        search: 'Sesha',
        searchPlaceholder: 'Sesha imikhiqizo…',
        itemsFound: 'imikhiqizo etholiwe',
        itemFound: 'imikhiqizo etholiwe',
        any: 'Noma yikuphi',
        allProvinces: 'Zonke izifunda',
        allCities: 'Izidolobha zonke',
        allSuburbs: 'Zonke izigaba',
        
        // ViewItem Page
        reviewsForThisSeller: 'Ukubuyekeza umbhali wento',
        addToCart: 'Engeza ku-cart',
        buyNow: 'Thenga manje',
        buyViaEscrow: 'Thenga i-Escrow',
        contactSeller: 'Xhumana nomthengisi',
        leaveReview: 'Shiya okubekuzwa',
        outOfStock: 'Kuphele isitoko',
        
        // CreateReview Page
        rating: 'Ivinini',
        reviewTitle: 'Izihloko',
        description: 'Incazelo',
        max100Chars: 'Umholo 100 izinhlamvu',
        max500Chars: 'Umholo 500 izinhlamvu',
        submitReview: 'Thuma okubekuzwa',
        
        // Settings Page
        profileSettings: 'Isetshenziselo yeprofayili',
        accountSettings: 'Isetshenziselo ye-akhawunti',
        updateProfile: 'Hlaziya iprofayili',
        changePassword: 'Guqula iphasiwedi',
        verifiedAccount: 'I-akhawunti yaqinisekisiwe',
        notVerified: 'Ayiqinisekisiwe',
        optionalVerified: 'Yengeziwe — qedela bonke isine yokuthola',
        status: 'isimo',
        firstName: 'Igama elokuqala',
        surname: 'Izithakazelo',
        username: 'Igama lomsebenzisi',
        email: 'Imeyili',
        emailNoChange: 'I-imeyili ayikwazi ukulungiswa.',
        dateOfBirth: 'Usuku lokuzalwa',
        mobileNumber: 'Inombolo yomakhalekhukhwini',
        mobileHint: 'Inombolo ye-SA engu-10 izinombolo',
        saIdNumber: 'Inombolo ye-SA ID',
        saIdHint: '13 izinombolo · kufanele ukuthelana nosuku lokuzalwa',
        location: 'Indawo',
        province: 'Umkhakha',
        city: 'Idolobha',
        suburb: 'Igaba',
        locationHint: 'Khetha indawo yakho eyalagela iTradeSA. Uma indawo yakho iyonakele, isinike.',
        reportMissing: 'Isinike indawo eyonakele',
        profileDescription: 'Incazelo yeprofayili',
        changePhoto: 'Shintsha ifoto',
        photoHint: 'JPG · PNG · WebP — umsindo ka-5 MB · okuzuzayo',
        saveChanges: 'Labela izinguquko',
        currentPassword: 'Iphasiwedi yamanje',
        newPassword: 'Iphasiwedi entsha',
        confirmPassword: 'Qinisekisa iphasiwedi entsha',
        updatePassword: 'Hlaziya iphasiwedi',
        
        // Common UI
        required: 'Idingekile',
        optional: 'Ingenaminingwane',
        verified: 'Iqinisekisiwe',
        save: 'Labela',
        cancel: 'Khansela',
        close: 'Vala',
        loading: 'Iyalayisha...',
        error: 'Iphutha',
        success: 'Impumelelo'
    },
    
    xh: {
        // Navigation & Header
        settings: 'Izisilelo',
        myListings: 'Iimilo Zam',
        history: 'Imbali',
        reportIssue: 'Bika Uvimba',
        signOut: 'Phuma Ngaphandle',
        
        // Account Setup Page
        welcomeToTradeSA: 'Wamkelekile kuTradeSA',
        completeYourProfile: 'Zalisani ikhente lakho',
        requiredFields: 'Imipu emakiwe nge-* iyadingeka. Zalisani zonke eminye imiba ukuze ube umthenguli oQinisekisiweyo.',
        requiredSectionLabel: 'Iyafuneka',
        optionalSectionLabel: 'Ekubeni encinane — zalisani ezine zonke ukuze kuvulwe isitati se-Verified',
        firstName: 'Igama lokuqala',
        surname: 'Isithunywa',
        dateOfBirth: 'Umhla wokuzalwa',
        mobileNumber: 'Inombolo yomnxeba oyintloko',
        saIDNumber: 'Inombolo ye-SA ID',
        location: 'Indawo',
        province: 'Iphondo',
        city: 'Isixeko',
        suburb: 'Isithili',
        profileDescription: 'Inkcazo yekhente',
        descriptionPlaceholder: 'Tshela abanye abathengeli into engakucingela…',
        uploadPhoto: 'Layisha isithombe',
        photoHint: 'JPG · PNG · WebP | max 5 MB · encinane',
        chooseProvince: 'Khetha iphondo',
        chooseCity: 'Khetha isixeko',
        chooseSuburb: 'Khetha isithili',
        reportMissingLocation: 'Bika indawo elahlekileyo',
        completeAccount: 'Qedela i-akhawunti',
        
        // Browse Page,
        
        // Login & Register Page
        login: 'Ngena',
        register: 'Bhalisa',
        emailPlaceholder: 'I-imeyile',
        passwordPlaceholder: 'Ipasiwedi',
        usernamePlaceholder: 'Igama lomsebenzisi',
        confirmPasswordPlaceholder: 'Qinisekisa ipasiwedi',
        dontHaveAccount: 'Awunakho i-akhawunti?',
        alreadyHaveAccount: 'Unayo i-akhawunti?',
        marketplace: 'Amagatsha okuthengelanisa',
        findTreasure: 'Fumana eyakho isinequandinginqandingi olulandelayo',
        filter: 'Chibela imikhiqizo',
        filterHint: 'Chibela iziphumo nge-indawo, intengo, noma uhlobo lokuhaula.',
        price: 'Ibanga leXabiso (R)',
        location: 'Indawo',
        deliveryType: 'Uhlobo lokuhaula',
        pickup: 'Zokuthatha',
        escrow: 'Escrow',
        clearFilters: 'Enza ngcaciso leyo yonke',
        noResults: 'Akukho imikhiqizo efunyanwe',
        noResultsHint: 'Zama ukulungisa iifilter zakho okanye ucaciseleni isakhiwo.',
        search: 'Khangela',
        searchPlaceholder: 'Khangela imikhiqizo…',
        itemsFound: 'imikhiqizo efunyanwe',
        itemFound: 'imikhiqizo efunyanwe',
        any: 'Nayiphi na',
        allProvinces: 'Zonke iifand',
        allCities: 'Zonke izixeko',
        allSuburbs: 'Zonke isithili',
        
        // ViewItem Page
        reviewsForThisSeller: 'Ukubuyekeza umthenguli',
        addToCart: 'Yongeza kwisitulo',
        buyNow: 'Thenga ngoku',
        buyViaEscrow: 'Thenga kwi-Escrow',
        contactSeller: 'Qhagamshelana nomthenguli',
        leaveReview: 'Shiya isixwayiso',
        outOfStock: 'Ikhonjiwe kuyo yonke',
        
        // CreateReview Page
        rating: 'Ukuhlolwa',
        reviewTitle: 'Umxholo',
        description: 'Inkcazo',
        max100Chars: 'Inombolo engu-100 izichazi',
        max500Chars: 'Inombolo engu-500 izichazi',
        submitReview: 'Thuma isixwayiso',
        
        // Settings Page
        profileSettings: 'Izisilelo zekhente',
        accountSettings: 'Izisilelo ze-akhawunti',
        updateProfile: 'Hlaziya ikhente',
        changePassword: 'Tshintsha iphasiwedi',
        verifiedAccount: 'I-akhawunti eqinisekiswe',
        notVerified: 'Ayiqinisekiswe',
        optionalVerified: 'Encinane — qedela zonke ezine nokuthola',
        status: 'isimo',
        firstName: 'Igama lakuthi',
        surname: 'Ifani',
        username: 'Igama lomsebenzisi',
        email: 'I-imeyil',
        emailNoChange: 'I-imeyili ayinakubuyiselwa.',
        dateOfBirth: 'Usuku lokuzalwa',
        mobileNumber: 'Inombolo ye-Cell',
        mobileHint: 'Inombolo ye-SA engu-10',
        saIdNumber: 'Inombolo ye-SA ID',
        saIdHint: '13 izinombolo · kumele zithelane nosuku lokuzalwa',
        location: 'Indawo',
        province: 'Iprovhinsi',
        city: 'Isixeko',
        suburb: 'Isithili',
        locationHint: 'Khetha indawo yakho eyagcinwe kwi-TradeSA. Ukuba indawo yakho ayikho, yibika.',
        reportMissing: 'Bika indawo engenakho',
        profileDescription: 'Inkcazo yekhente',
        changePhoto: 'Tshintsha umfanekiso',
        photoHint: 'JPG · PNG · WebP — max 5 MB · encinane',
        saveChanges: 'Yila izinguquko',
        currentPassword: 'Iphasiwedi yangoku',
        newPassword: 'Iphasiwedi entsha',
        confirmPassword: 'Qinisekisa iphasiwedi entsha',
        updatePassword: 'Hlaziya iphasiwedi',
        
        // Common UI
        required: 'Iyafuneka',
        optional: 'Encinane',
        verified: 'Iqinisekisiwe',
        save: 'Yila',
        cancel: 'Rhoxisa',
        close: 'Vala',
        loading: 'Ilalithayo...',
        error: 'Isiphosiso',
        success: 'Impumelelo'
    },
    
    nso: {
        // Navigation & Header
        settings: 'Dikgokagano',
        myListings: 'Dipalanka Tša Ka Nna',
        history: 'Histori',
        reportIssue: 'Bega Bothata',
        signOut: 'Nyalamela',
        
        // Account Setup Page
        welcomeToTradeSA: 'Maboya ka TradeSA',
        completeYourProfile: 'Gapedisha profaele ya gago',
        requiredFields: 'Dipolante tšeo di nago le * di a swanela. Tlatšetša dibišwa ka moka tšeo e se nago seswantšo gore go ba setšupi sa Warranted.',
        requiredSectionLabel: 'Se a Swanela',
        optionalSectionLabel: 'Se se nago seswantšo — tlatšetša bonnye ka moka go go re-akhat seemo sa Verified',
        firstName: 'Leina la Pele',
        surname: 'Moranaka',
        dateOfBirth: 'Lešomo la Bopiwa',
        mobileNumber: 'Nomoro ya Seluwalete',
        saIDNumber: 'Nomoro ya SA ID',
        location: 'Lefelo',
        province: 'Profense',
        city: 'Toropo',
        suburb: 'Distreke',
        profileDescription: 'Tlhaloso ya Profaele',
        descriptionPlaceholder: 'Bitša bagwetši ba bangwe ka konokono ka gago…',
        uploadPhoto: 'Reka Seswantšo',
        photoHint: 'JPG · PNG · WebP | max 5 MB · se se nago seswantšo',
        chooseProvince: 'Kgetsi profense',
        chooseCity: 'Kgetsi toropo',
        chooseSuburb: 'Kgetsi distreke',
        reportMissingLocation: 'Bega lefelo leo le le lahlago',
        completeAccount: 'Gapedisha akhawonthe',
        
        // Browse Page,
        
        // Login & Register Page
        login: 'Tsena',
        register: 'Ngwadiša',
        emailPlaceholder: 'Emeili',
        passwordPlaceholder: 'Phasewete',
        usernamePlaceholder: 'Leina la modiriši',
        confirmPasswordPlaceholder: 'Netefatša phasewete',
        dontHaveAccount: 'Ga o na akhaonte?',
        alreadyHaveAccount: 'O na le akhaonte?',
        marketplace: 'Mmtebele wa Lapile',
        findTreasure: 'Akanya tšeo e maratago a gago a sa le fapano',
        filter: 'Sela semanakane',
        filterHint: 'Fokotsa diphelo ka ndinao tša lefelo, tefo, noma mofuta wa kgaolelo.',
        price: 'Serangano sa Tefo (R)',
        location: 'Lefelo',
        deliveryType: 'Mofuta wa Kgaolelo',
        pickup: 'Go Bolawa',
        escrow: 'Escrow',
        clearFilters: 'Phakamela leselanegane la go oketšang',
        noResults: 'Ga go na semanakane se se hweditšwego',
        noResultsHint: 'Leka go lekantšha sela sa gago kana go hlakanya potšišo.',
        search: 'Batla',
        searchPlaceholder: 'Batla semanakane…',
        itemsFound: 'semanakane se se hweditšwego',
        itemFound: 'semanakane se se hweditšwego',
        any: 'Lefeela le lefeela',
        allProvinces: 'Diprofense tšohle',
        allCities: 'Melopostaria yohle',
        allSuburbs: 'Distreke tšohle',
        
        // ViewItem Page
        reviewsForThisSeller: 'Diphithišo tša mmoletši yothe',
        addToCart: 'Oketša go karete',
        buyNow: 'Reka gone',
        buyViaEscrow: 'Reka kwa go Escrow',
        contactSeller: 'Ikopanya le mmoletši',
        leaveReview: 'Tloga Phithišo',
        outOfStock: 'Go fitšwe mo trokong',
        
        // CreateReview Page
        rating: 'Sekgahlo',
        reviewTitle: 'Sesikolo',
        description: 'Tlhaloso',
        max100Chars: 'Ka nako ya 100 ditharasita',
        max500Chars: 'Ka nako ya 500 ditharasita',
        submitReview: 'Romela Phithišo',
        
        // Settings Page
        profileSettings: 'Dikgokagano tša Profaele',
        accountSettings: 'Dikgokagano tša Akhawonthe',
        updateProfile: 'Apara Profaele',
        changePassword: 'Bapale Phasewete',
        verifiedAccount: 'Akhawonthe ye Netefatšitšwego',
        notVerified: 'Ga se Netefatšitšwego',
        optionalVerified: 'Se se nago seswantšo — kwala ronwane tšohle go hwetšwa',
        status: 'boemo',
        firstName: 'Leina la Mathomo',
        surname: 'Lefoko',
        username: 'Leina la Mošomišani',
        email: 'Imeyile',
        emailNoChange: 'Imeyile e ke tšoeletšwe.',
        dateOfBirth: 'Letsatsi la Lebelo',
        mobileNumber: 'Nomorofono ya Sellolage',
        mobileHint: 'Nomorofono ya SA e le 10',
        saIdNumber: 'Nomorofono ya SA ID',
        saIdHint: '13 dinomorofono · go swanela gore le duele letsatsi la lebelo',
        location: 'Lefelo',
        province: 'Lefelo',
        city: 'Toropo',
        suburb: 'Sedidi',
        locationHint: 'Kgetha lefelo la gago le le filwe go TradeSA. Ge le se le lefelo la gago, l era lo.',
        reportMissing: 'Era Lefelo le se le leng',
        profileDescription: 'Tlhaloso ya Profaele',
        changePhoto: 'Bapale Seswantšo',
        photoHint: 'JPG · PNG · WebP — ka nako ya 5 MB · se se nago seswantšo',
        saveChanges: 'Boloka Diphapano',
        currentPassword: 'Phasewete ya Jaanong',
        newPassword: 'Phasewete e e Mošwa',
        confirmPassword: 'Netefatša Phasewete e e Mošwa',
        updatePassword: 'Apara Phasewete',
        
        // Common UI
        required: 'Se a Swanela',
        optional: 'Se se nago seswantšo',
        verified: 'Se se Netefatšitšwego',
        save: 'Boloka',
        cancel: 'Hatangela',
        close: 'Tsena',
        loading: 'Leina le le oketšang...',
        error: 'Seswantšo',
        success: 'Katlego'
    }
};

/**
 * Get all translations for a specific language
 * @param {string} lang - Language code (en, af, zu, xh, nso)
 * @returns {object} Translation object for the language, or English as fallback
 */
window.getTranslations = function (lang) {
    return TRANSLATION_DATA[lang] || TRANSLATION_DATA.en;
};

/**
 * Get a single translation key for a specific language
 * @param {string} key - Translation key
 * @param {string} lang - Language code (optional, uses current language if not provided)
 * @returns {string} Translated text, or English fallback if not found
 */
window.translate = function (key, lang) {
    lang = lang || window.getCurrentLanguage();
    const translations = window.getTranslations(lang);
    return translations[key] || TRANSLATION_DATA.en[key] || key;
};

/**
 * Get current language from localStorage
 * @returns {string} Language code (defaults to 'en')
 */
window.getCurrentLanguage = function () {
    return localStorage.getItem('selectedLanguage') || 'en';
};

/**
 * Set current language in localStorage
 * @param {string} lang - Language code to set
 */
window.setLanguage = function (lang) {
    if (TRANSLATION_DATA[lang]) {
        localStorage.setItem('selectedLanguage', lang);
    }
};

/**
 * Get list of available languages
 * @returns {object[]} Array of language objects with code and name
 */
window.getAvailableLanguages = function () {
    return [
        { code: 'en', name: 'English' },
        { code: 'af', name: 'Afrikaans' },
        { code: 'zu', name: 'Zulu' },
        { code: 'xh', name: 'Xhosa' },
        { code: 'nso', name: 'Northern Sotho' }
    ];
};

/**
 * Apply language translation to the entire page (all data-i18n elements)
 * This is the main function that all pages should call
 * @param {string} lang - Language code (optional, uses current saved language if not provided)
 */
window.applyPageLanguage = function (lang) {
    lang = lang || window.getCurrentLanguage();
    
    // Save language preference
    window.setLanguage(lang);
    
    // Translate all elements with data-i18n attribute
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        const translated = window.translate(key, lang);
        
        // If element has children (like labels with <span>), only update text nodes
        if (el.children.length > 0) {
            let textNode = el.childNodes[0];
            if (textNode && textNode.nodeType === Node.TEXT_NODE) {
                textNode.textContent = translated;
            } else {
                el.textContent = translated;
            }
        } else {
            el.textContent = translated;
        }
    });
    
    // Update all select/input placeholders
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        el.placeholder = window.translate(key, lang);
    });
    
    // Update select option placeholders
    document.querySelectorAll('[data-i18n-option]').forEach(el => {
        const key = el.getAttribute('data-i18n-option');
        el.textContent = window.translate(key, lang);
    });
    
    // Update value attributes
    document.querySelectorAll('[data-i18n-value]').forEach(el => {
        const key = el.getAttribute('data-i18n-value');
        el.value = window.translate(key, lang);
    });
    
    // Update header language selector
    const headerLangSelect = document.getElementById('header-language-select');
    if (headerLangSelect) {
        headerLangSelect.value = lang;
    }
};

/**
 * Initialize page language on document load
 * Call this in your page's script section or add to window.addEventListener('DOMContentLoaded', ...)
 */
window.initializePageLanguage = function () {
    window.applyPageLanguage(window.getCurrentLanguage());
};
