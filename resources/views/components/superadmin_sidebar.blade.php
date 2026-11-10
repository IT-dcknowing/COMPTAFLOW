{{-- Le sidebar superadmin utilise les mêmes styles que le sidebar utilisateur définis dans sidebar.blade.php --}}

<div class="sidebar-new">
    <div class="sidebar-header">
        <div class="brand-container">
            <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEsAAABHCAYAAABPjLqRAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAACdqSURBVHhedXwHmKVHdeV5uXNP6umeII00M0ISCAmhgKyEQF60AoNAfF4wCwizFhtgwSbZRgvGgEEkAYuEcYAlfGKxZEA4SAgJA9KgnMMoTNSMJnT3dA6v3+sX9pxz6+9p4W/rdf2Vbt1769StW1X/ezO5qZlqO5cDWu0Wcq0c2sy3220o5NigvNpVo8gaNbDQJG0BuUTrPkt9c6YT4xwZuirfBiXgvp0NnLOF/dTJfEiXelALFvPsR5lq91Pykx6mMGc/paw/eVOZl5NWG/l8DtQQBfIzb7a18i3kUTDP0I3kpIt+iYZJaMd2EhAWpi3LzecpiPlgyAGpjwTlqEFLDKWJ+lodZlQmPdXRY0lwEFGxPEWRRqnqPHB148DyktzK+mVJjhMVdOYtHdSPHylr0QwajwKhREH8OSorz5Ca2J/ULKhePAoCk2MhXqwkfa4oRcTE7RKnSWhrvKJj/wINQDwkX/zyBfJiXV76k8L6q7tiS0BwZtCkokqJpFVmxxxnpkWCsB5XWYBnncLQarCv2lvsThoxY2h7NkK4FRep6slfn4JEm5x8NECJMyzURw3kpQmUVEfxJageKMuefs1901zjj22yEvdnlG14LCwKaGWcZ2uOY/V4E11MMNs1fv1p7MSQy3A+GzsJ1FUCZHQBgtm5IxMN1ABEXZPMFhs1cQshTA0cKVJP07OaeZdw754mzjpOFa40OO4jsNlLvQOWCM7pwejhsJ/nVjomC8pbb1oFrblY4KiSxak2evFp5SNI22yxJSIGgScerEjilXi6ZMn6yGeFQlKUOQkWoloONIMATmNuYd/4Eew90sTzwwuYmAPmFopoNAqmaLJdliHTMQ4JhJa4ezCWgukq0NvhbFSLezRFMCk1ybq4e/Aymcp8RB1lsr5A0ywUcygV2qiUF9FbaWFodRlb1peweWAVOisV9TS44h2MXZH4SRgBpFABL70kX4tBlMaZFbnJqTkCR/xsysZPCQcu90hAag065WHc9ug8RkZXoYOc+ruArlKOswgqKAF2CZkKFp2FrE65UCry1vnfBbWrYVkjsyod7RmPBF3kGbWSGnzUGm1U6y1Mz7UxW2uh0j2NV768gItPXYfBFT0EVrxkVUscHMQ/T1QEviYis0OhIgA1MblJL8NsaTFJHOZrNTy4ZxY/e2gOh4Y70F/JY6A7hwp9pGiaVKxBS9JS1Mq0aFmTmikwA8OJmlTnGgaVU8aLRMT884wur0shsi8sL2tmCN6yhDwf8m/0y6RpY2qhjeHJJjo6F/DqM8v4vfMHMdDD2U7LMibQvd1Xu3GUXbUEoBfixMwsKVIXLSU2yrV+5869uPORbqzuLGJlB32TZmu+gbn5FqqcsfoiZzIBpeUn0NrcKSTGvktlFjQNGXiRT6qYUH/RFpUKqnHywtQho0124YLKWcoosJjk6UbKZTBybBxctd5EbbGBM16aw4f+YAM2rlnhMavNS5r6agNrt7Wbs48NiJwSkY5IufGp2ba2Wu9EbKsRhe9t24NtD63E1oES+spNHB6r4/B4ANXgjiMwtN2zi4U4JTPXa6dhkN7iqXEobwBTaj+mgkLWHqXgw9Q7scqJwEeLqDLPSFUXBScqmiirZ6Q6WnqyNMnVfveKM2r45LtPwipamKxYLsRHJLUz+hhBrFRlsQKLmdz45Kx9lhgvNpu47fEx/OSOHF4yWOJJoImdB+jMp5toLCYrYuflMSxIFpVSCnN91p4NxBIoR6C53S2ui7yC+opGBFmNiV0McKI+q3OF/pba9JAu5uySD84GhHU5+eJFXHJRA1ddcRo6S9xtOP6g0ebEVJ3YQR8FqaRKLslUwZrxuUX86tEazjmujJ5yG3sO1jA60cDiIs9NHLkGryhnaiAMXgy+pSWousTL9RamWcnyESxcdYyePh1gTSNlVZfFIHZfMV/ikQEV1daFGX20eKIsPdm3ySh92CA/q1R4/Oy2Bm59cE9iyQqmPkOqyIJ4NTlAnxHd56jTZ2jh7mdGkKt3or8jh4OjdYxNNSiBrEhspbjEpIgKtigzTYMRe9V7cMuDbDkAMxCcHJ+wdTLm+tBhzzcGAuZ60wlA8WRUoqBZj0xE/kknCcskRlk6uDoeolPegfpzPNrBFW/91RTGZ+YIaIxFfk4D5BDUjSoEL2EjGPNxIm9xCTbwyyfqGOgqYHa+iYNH6rYoHSksXBwYnCd45ihGKQ3AgsbgJAB8ttFADUwCgbFC61+zIodjBvM4diiPDQN59PfmUeR5yYdA9TOgKU+eISNCDCLCUl7N1ilFJwk8RU62rYzWtrKngGd2zWP3wWnyZ71MjgxMb4tQkXKdMrLKPkvjferQfnzh+gou2FKiRS3ikR1Vns4DKDtzC5J1ydRTXh1T3tHCWKdq8acwLbUSraeD58IXbczj/JdWcNrWCoZWFdBREqmUjKDlPVtt47nDDdy7vY476BL2DXMi6S+b9Ke2XA1EqQQyhIVnA8zalsYXZSaWExW8XjVw7JpFjE/P4Z1v7cMfvu50U2h9yri444mQcypgOVqtW53NxqdmLPamB/fill9244ITS3hsV5UK1wyMZyKLLssoBVACRszVZvWYpwBZjkor+/J42dYyzjyxwLSI1b086XPT8DHDUhWy9GhOvXUqb3HD2D/SxL1P1XHvk3U89IysPaYklnzi45Qds7JD1FmraPSfANH7iA2rFtFcnMfJpzXw5fdfxCZSakWQVvLTph6ARTeCNTHNVdXCV27ejb07+3AGnfvd22cxxoOcZHjF2VcpSgGpmjlzcRRwKS9zZiKdLj6jjN+/sAtDK9u0LEqwBWh2RS8KxQhHcwwuiFac9Qit52s57D3cxjd/PI3HdtaDR/wxKHMUqLA2a5YRuN1AuaGJgb4aN7FF5PtGceNnX285PiKoWQ91dap+cu6Meg2jw+TwdMGn8xr9VK0ejMVXPSjbiqifQbFSZquKYM6oJX78ugI+d2UfPvqfurFxddMm36QpH/V96ive4hdRDVmb/OfROkYNnLGz1MTJx7Zxzfv7ceUbutDfHVzYKH9gehfVhxJc8JOfaFIjhxNlnk9Rpn+c4CbWaDTstxQ0Fr288LXHaDHoVM/B0eHnsLBYxfQsb+xkVCdYDd6vLEDRiqS8OkcxhCsVd67xQjGPS87uwCfe2YPTjs+hzsOtNo9YurIopswLNNe5XmVt5yrTkkW/rM7R9NFH/iOPBt72mk588X0rcPbJRfNusq8nRJsV6fgwgCFDumafsGrVNejktd/Uqjle7ufSEmIb+8SZS0bCSm020UjLYkZvDJqL3LpZRZCTT3Ffxmz5iVxRAkmZysKqxCvFey7rxvve2EXz5p2RO2tYSAgJBSO6n9uUss6yFFU2eUSH364MWoG2dUMOH//Dfm4YRepOfgJrCbBEnT2WukfePChXxtRu5Hh9q4oz66WvQhgFrYAp8/wzvXDTizptp+Kl64wavM2yb8xuYKsgGqfiQKRytKg/uLgTrz2bJ/6WrEkHsxhUKKCoqsgvX45Kxcmf1B5RNKJV3uxCJ5ezCHTzzvrp/7oab7ywkwMXSLRO6q/Ua+mFqEWqbKoqeOvTklxcIokdPgp+AcgGGwfz9NRSjACJWgiqg2SYiHkzZt7gybEzZZ2A0ivXK/5jF950Ps8FVDDObJkAUqm/89kEaCBxDKhWq5iansb4+BQmGKempjE/P8/djruUJsi8JDv0E7MoZ3XBV6fm91zey+NIPo4XlpEsjDSZfMkVEOxuvZT6kMz2RdGTxq/D/REUoo28aPQ6OTc6Nt6e4pr90LdnMVQooq8zhweeniPatCbtguyiLVxOzkuQAnxoZHrp73Tgg7/fg4UFHTMS4PqTcpYhJVUV9Qu1GsbHJrH/wCSe3jWFXXumMTw6bx/Z01PB8ZtW4sUvWoktx/ViYHUPurpoMenMIx5W/AWBg3FlDj/+xRS+8oNJAq06Rh9f2KhdJ3U0J1qIlOquLOLkjYt45vlD+M41L8fmgWNj1RFQwyQe7q+SyhzzER4dJuam8cFvTRGskq86Dzwzj7rBkmUEwuIUYJEjFdmyoYSr3tGDwf7swBgz6UhqzzDzAnCGV4qnnx3BtvsO4KHHx7Fz7yxmZ7n9k2feb+N05ylYUb1QHFjdhbNPG8S5Z63GmS9dhZUrCJpuAFJDzBUSAFl45Ol5fOiaI/Q/2tl0dRLfmFRbEIMHLwZcsj3lOk7a2MCzBw7h218+HVvWHmeeekGl1LujJp+pnT4/erMVZk9FA5ygWYpiL8BSPkIOb7+kE0Mr5BoEVAIps6gE1CJ3iwMHhnHDTc/iU199HN//0V48+vQsqo1OFLsGUe5dj1LPeqbrUO4ZQqVnEPnOQYzN9eJnd1fxhb8/gI9/dQe275jCYtpdYyok5GiMwchPcXey35JLYKpP0o0NLIV+yx5si6x3QP/pQUodntWXtxhxss9Sd86Bg8GiYFsQ2+UR5Kfc3xSCPIcLTi3hgpdUQiHRi6k/coEq02nyIPPMs4dw9dcfx9e+/RT2HeRSLfSj2DlAoAZQ6l6NUudKlhVXoNjBqFTlrhUoVHoIagevXTn82VcO4Zf3TKK2QEds/Y5+ZLmasH0HZuyoJbzdjk0mZl6DUJ2sSiEAj/F4RAysk/WpT5ZnC205LEzAsM5fjBz1NcqrTcwV2SdLDVTeV5h3v66XB9e66dzRnZUYXjvpp545wyPjzLfxx996DHdv6yBwPPjVpGwMZil4AOoSfiGsQRuBjhmxQyrf4gm6p7yA80/vxKUXDuHUk9fw6KDjRcxOFoKzGaopQhLn4Qgot8vi6JtGJvDru3bi9m378ewuXnV0+OSRIF/oIVar6MtXMHbRstJxwQ5d7kUrIBORBHHwpfwsugqT6Fw9i1v/5h0+Jlg/yovdWfqyR6YzU+6GBItqfemmx3DDjyvesRr+UYjASdpb+SynOi0HzbYAEmg83CjP7b/VJHhNXjkWq9zEqzhxUwtve8MmHgWOQVeX3qQGb/Naxj9yoWwWFhfrOHR4FDf/4inc8m97/cpY32saBFoTirywl1caqFxRQMUSpCNNNARKP25R8JjJW4Pm5JYwjWJ7HCeeAnz3c28hpPzQHelFofvqXOWVRPDEh4GWdYQc8rjlsR34xBd1rwoBWjIegfVPeT6iSmCpPvMvTMGZ9lVDdcoLMN7hFmd4hJrB0KoGTj+5FyduWYX1Q73o6+1AZ0e8bpEMLbU6T5QzcwsYG5/F3n1H8Oj2w3hq1ySXtAYSfjKnd1N5LrcSgSr2R8rjgoDSUWHJojxAKa+8lXfRA2nVDVRucQyXv2UDPv5fLnGzzlZqlzGpv5afr0iulmXxBC8uo9NTeNOfP4WZEd3SwxcFPklQSrOni/YzWUwg2f9w+jPAeBxoNasEbI46ztG5zqNSWERHpYGOUoPnKr0p0Lkq/l1jdaGJao1nME5aLqf3UFoeAkFWQ0AMjPyTLIm6Zn7KS099tLMJlQSWAsFynYpUvN2cQ35xmAY4hb+66ly87oJTTc3OJOU6YxpvIFIftQq4YfosDs87xEe+dQ9+dosuWWUSaybNXfzZJy2Vow/Wq035sLAl4Ghd1MiplmabS9O/N2gsGEDNbFuxreUrn6cXivp+j32XBib5BMBgaXZlOQSswMOm/ZLyOqELqADJVuWBqb90V8oloER1CloJi9wYFg5g0+Y8rvvM67Bl4zqSyKJESDYaknmmPgbABywBRVI2vPm8jehdMY8mfYVmOgZvarssg2OgAqQASlFCqKTtlSy9ZBj9ekQ7lb7S70ah3Id8ZSVyFTrkDl6TKgNMea3pHGR+CPnOdSh0rGfdOpbXRnuFdOU1duK5ipx4L40oWZSXnkCSHKZhH1Yn9EoJoxPqKytv1ae5E1ZxzjmDOHaIvLMxOLCUdlDCGmPU0IhP3rukCgTm1C2DeNUripz9GR76qmRIi2C9fbuAs1QKVGIBSQlFz1yaDYGmWfZuRcvw+Uf3tfgdhF/IlTlogVfqZ6of3NJRy1kzn2MMx612OXHSqo+XX/zghBVLciiIsiU3dInAHK3KallBbkQNXshrR9Coj6FvfR3vuvws+8zokxmG2BAwDjLPI1S8mFTkU0bv0VNgV6mMd1+2GRs3TqJZn8JibY6g0e/I/4haTt0f21l8KCETEjWheOwkUoRptqRkaRro0jLiwH0+ov8p6feljMwrui5F/XbCfWRJWpYGKAGV5BmRJT1iYrUyPMkEqlknUAujaFYPortvFl+46mIcv3aQ1N4Hl7QOThyFrMsWFkzFKzc8OiFbYYHCNXB+nti3D5+45n488QQ7F2T2GgCPFZmpi8cSEycOVi4yKZGywdNi5fhVTvXRxrz5mYDRGcZM0dSYWY+iE+lB/VSf+aNlwXwlR/5ycQ7NGv3U4hGsWF3DB/7HuXj76w/i0VUgUTexoChZEbmGChaT+JuAyfCRCfrm9L2aavhHt4w9PN984mu34J476YQLfZxQ/SsH7UTJ/BnVJ8GQospKVRSvrF7PKJuCWe06QRupuLiYQrQtD6GhZUTGY1BZFQGOyko5Am0oTW4oi9MEa5LyZrBiYwufv+oyXHjGy1CWa2BXHzEIqt+aSDGnegWVLW8G1Qmj4dFxUlA1PS2MYjXZbJ9dqOL6W+7ET/95F/Y9N49qlcsBFeomwGLncRfxdGZZ1lammAQ6SEjKpswSKFl9xiA7GwkZs8jA0IPKOWQ0kqOg3TeOLWhVWT/Ha9wCBtdWcO5FW/C+d7wWxwwMsZeMQ6skTRJD9nWX37YaMIolWHIz9lsqG6xMQTo07wDq7aDLJnBwdBiPP7MfD2/fg2d2j2IfD4zjUwuoVXkU1TspvY8mk3hmzARD1Dgwmynm+ozMgRmC4n+yxtILmlTSLDKxVabayGQltesVdcv/pUp/fwfWr12BE09Yj5eedCxectJxOOHYY1Apc8PRvZH8hE34VfZmXjmBIhdhd6MJEEiWT8nM+nfwcS7SGwadxtVJQ/Y5XaRWKU70VJa02g8avHfMV+ewUF9AfTH+mYhhNh3/pA3r9AnPYLG477kmzjqW3D1b7KHlLLBVTLLCUmIE+m+pMl/oibALoF4kVK14i5VeXVcqFXR1dHGH0xKLf6ma+Ryf6hMXfdng/zZBTa4iM530WSWqFst5/TMc/jmwOVcgD4GlQqz5MFBn9dHARctZs6LKizbVC0DJ0cP9xEjB/RlEZ4VMZEDv2NnGhS+K65R4qi27WnhySKoW3VH11sEpa5R64ApMNQ7x8H0uCTzaLKHiKanKR39VxLsuNZODlqsoAqWgddkJ6f1IffzyT0VpGAOSNSSS+PkNC1rf8domhh0DDX7BIbyX506HcfWRfFloIvLMuhxA8BCRdaUwQs06RyoY/zua0jTbStkWr5/FnDrIOpRPugdo0U+6agOx+nnxjrZIbf+cCCoqwByDTfCTvaosOUYmdGQw4GRDQq1T9RUgMROeMVNKmShrMHaGSenomylClchGF1KtHMkTgNYhKSm/6Hkxj1DASqou1fuA6z6y7rAiWVyeHW1NzOeKeqQu9DHiKZD15QlnOfgLMDvyYOdx6cDgZnZQZ3XkX+ggekljcLtHx2a5C+D/AZ3a7qk1NVfSAAAAAElFTkSuQmCC" alt="Logo COMPTAFLOW" class="brand-logo-image" width="48" height="48" style="width:48px;height:48px;object-fit:contain;display:block;flex-shrink:0;">
            <div class="brand-text">
                <h1 class="brand-title">Flow Compta</h1>
                <div class="role-badge-sidebar">Super Admin</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        {{-- SECTION 1 : PILOTAGE --}}
        <div class="menu-section">
            <div class="menu-section-header">Pilotage</div>
            
            @if(auth()->user()->hasPermission('superadmin.dashboard'))
            <a href="{{ route('superadmin.dashboard') }}" class="menu-link-new {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-rocket"></i>
                <span>Tableau de bord SuperAdmin</span>
            </a>
            @endif

            @if(auth()->user()->hasPermission('superadmin.activities'))
            <a href="{{ route('superadmin.activities') }}" class="menu-link-new {{ request()->routeIs('superadmin.activities') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Suivi des Activités</span>
            </a>
            @endif

            @if(auth()->user()->hasPermission('superadmin.reports'))
            <a href="{{ route('superadmin.reports') }}" class="menu-link-new {{ request()->routeIs('superadmin.reports') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line"></i>
                <span>Rapports Performance</span>
            </a>
            @endif

            <a href="{{ route('notifications.index') }}" class="menu-link-new {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                <i class="fa-solid fa-bell"></i>
                <span>Notifications</span>
                @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                    <span class="badge bg-danger rounded-pill ms-auto">{{ $unreadNotificationsCount }}</span>
                @endif
            </a>

            <a href="{{ route('superadmin.honoraires') }}" class="menu-link-new {{ request()->routeIs('superadmin.honoraires*') ? 'active' : '' }}">
                <i class="fa-solid fa-money-check-dollar"></i>
                <span>Honoraires & Abonnements</span>
            </a>
        </div>

        {{-- SECTION NOUVELLE : ADMINISTRATION INTERNE --}}
        @if(auth()->user()->hasPermission('superadmin.secondary.index'))
        <div class="menu-section">
            <div class="menu-section-header">Administration Interne</div>
            
            <a href="{{ route('superadmin.secondary.index') }}" class="menu-link-new {{ request()->is('superadmin/secondary-admins') ? 'active' : '' }}">
                <i class="fa-solid fa-user-tie"></i>
                <span>Super Admins Secondaires</span>
            </a>

            <a href="{{ route('superadmin.secondary.create') }}" class="menu-link-new {{ request()->routeIs('superadmin.secondary.create') ? 'active' : '' }}">
                <i class="fa-solid fa-user-gear"></i>
                <span>Créer super admin secondaire</span>
            </a>
        </div>
        @endif

        {{-- SECTION 2 : GOUVERNANCE --}}
        <div class="menu-section">
            <div class="menu-section-header">Gouvernance</div>
            
            @if(auth()->user()->hasPermission('superadmin.entities'))
            <a href="{{ route('superadmin.entities') }}" class="menu-link-new {{ request()->routeIs('superadmin.entities', 'superadmin.companies.*') ? 'active' : '' }}">
                <i class="fa-solid fa-sitemap"></i>
                <span>Gestion des Entités</span>
            </a>
            @endif

            @if(auth()->user()->hasPermission('superadmin.users'))
            <a href="{{ route('superadmin.users') }}" class="menu-link-new {{ request()->routeIs('superadmin.users', 'superadmin.users.*', 'superadmin.admins.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-shield"></i>
                <span>Gestion Utilisateurs</span>
            </a>
            @endif

            @if(auth()->user()->hasPermission('superadmin.accounting.index'))
            <a href="{{ route('superadmin.accounting.index') }}" class="menu-link-new {{ request()->routeIs('superadmin.accounting.*') ? 'active' : '' }}">
                <i class="fa-solid fa-calculator"></i>
                <span>Gestion comptabilités</span>
            </a>
            @endif

            <a href="{{ route('superadmin.packs') }}" class="menu-link-new {{ request()->routeIs('superadmin.packs*') ? 'active' : '' }}">
                <i class="fa-solid fa-gem"></i>
                <span>Offres & Abonnements</span>
            </a>

            <a href="{{ route('superadmin.liaisons.index') }}" class="menu-link-new {{ request()->routeIs('superadmin.liaisons.*') ? 'active' : '' }}">
                <i class="fa-solid fa-link"></i>
                <span>Liaisons SELFLOW</span>
            </a>

            <a href="{{ route('superadmin.affectations') }}" class="menu-link-new {{ request()->routeIs('superadmin.affectations*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-plus"></i>
                <span>Affectations</span>
            </a>

            <a href="{{ route('superadmin.archives') }}" class="menu-link-new {{ request()->routeIs('superadmin.archives*') ? 'active' : '' }}">
                <i class="fa-solid fa-box-archive"></i>
                <span>Archive des suppressions</span>
            </a>

            <a href="{{ route('superadmin.corbeille') }}" class="menu-link-new {{ request()->routeIs('superadmin.corbeille*') ? 'active' : '' }}">
                <i class="fa-solid fa-trash-can-arrow-up"></i>
                <span>Corbeille</span>
            </a>

            <a href="{{ route('superadmin.mot_de_passe') }}" class="menu-link-new {{ request()->routeIs('superadmin.mot_de_passe*') ? 'active' : '' }}">
                <i class="fa-solid fa-key"></i>
                <span>Mon mot de passe</span>
            </a>


            {{-- SECTION : CRÉATION RAPIDE --}}
            <div class="menu-section-header mt-4">Création Rapide</div>

            <a href="{{ route('superadmin.companies.create') }}" class="menu-link-new {{ request()->routeIs('superadmin.companies.create') ? 'active' : '' }}">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Créer Entreprise</span>
            </a>


            <a href="{{ route('superadmin.admins.create') }}" class="menu-link-new {{ request()->routeIs('superadmin.admins.create') ? 'active' : '' }}">
                <i class="fa-solid fa-user-shield"></i>
                <span>Créer Administrateur</span>
            </a>

            <a href="{{ route('admin.secondary_admins.create') }}" class="menu-link-new {{ request()->routeIs('admin.secondary_admins.create') ? 'active' : '' }}">
                <i class="fa-solid fa-user-check"></i>
                <span>Créer administrateur secondaire</span>
            </a>

            <a href="{{ route('superadmin.accounting.create') }}" class="menu-link-new {{ request()->routeIs('superadmin.accounting.create') ? 'active' : '' }}">
                <i class="fa-solid fa-calculator"></i>
                <span>Créer comptabilité</span>
            </a>

            <a href="{{ route('superadmin.users.create') }}" class="menu-link-new {{ request()->routeIs('superadmin.users.create') ? 'active' : '' }}">
                <i class="fa-solid fa-user-plus"></i>
                <span>Créer comptable</span>
            </a>

            <a href="{{ route('superadmin.habilitations.index') }}" class="menu-link-new {{ request()->routeIs('superadmin.habilitations.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-check"></i>
                <span>Modification Habilitation</span>
            </a>

            @if(auth()->user()->hasPermission('superadmin.access'))
            <a href="{{ route('superadmin.access') }}" class="menu-link-new {{ request()->routeIs('superadmin.access*') ? 'active' : '' }}">
                <i class="fa-solid fa-lock"></i>
                <span>Contrôle d'Accès</span>
            </a>
            @endif

            @if(auth()->user()->hasPermission('superadmin.switch'))
            <a href="{{ route('superadmin.switch') }}" class="menu-link-new {{ request()->routeIs('superadmin.switch*') ? 'active' : '' }}">
                <i class="fa-solid fa-repeat"></i>
                <span>Switch Entreprise</span>
            </a>
            @endif
        </div>

        {{-- SECTION 3 : GESTION DES TÂCHES --}}
        <div class="menu-section">
            <div class="menu-section-header">Gestion des Tâches</div>
            
            {{-- Tâches Administratives --}}
            @if(auth()->user()->hasPermission('superadmin.tasks.index'))
            <a href="{{ route('superadmin.tasks.index') }}" class="menu-link-new {{ request()->routeIs('superadmin.tasks.*') ? 'active' : '' }}">
                <i class="fa-solid fa-tasks"></i>
                <span>Tâches Administratives</span>
                @if(isset($tasksSentCount) && $tasksSentCount > 0)
                    <span class="badge bg-soft-primary text-primary rounded-pill ms-auto">{{ $tasksSentCount }}</span>
                @endif
            </a>
            @endif
        </div>
    </nav>

    @auth
    <div class="sidebar-footer">
        <div class="flex items-center justify-between">
            <a href="#" class="user-profile-mini">
                <div class="user-avatar-sidebar">
                    {{ auth()->user()->initiales }}
                </div>
                <div class="user-info-sidebar">
                    <span class="user-name-sidebar">{{ auth()->user()->name }}</span>
                    <span class="user-role-sidebar">Super Admin</span>
                </div>
            </a>
            <a href="#" class="logout-btn-sidebar" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="fa-solid fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    @endauth
</div>
