import React from 'react';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import InfoRow from '../../components/InfoRow';
import Pill from '../../components/Pill';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function ProfileScreen({ navigation }) {
  const { currentRole, currentUser, logout } = useAppContext();

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="profile" />}>
      <HeaderBlock
        eyebrow="Account"
        title="Profile summary"
        subtitle="This role-aware screen shows the data that should come from the PHP profile endpoints later."
      />

      <SectionCard title={`${currentUser?.firstName || ''} ${currentUser?.lastName || ''}`.trim()} subtitle={currentUser?.email}>
        <Pill label={(currentRole || 'guest').toUpperCase()} />
        <InfoRow label="Mobile Number" value={currentUser?.mobileNumber || '-'} />
        {currentRole === 'parent' ? <InfoRow label="Address" value={currentUser?.address || '-'} /> : null}
        {currentRole === 'driver' ? <InfoRow label="License Number" value={currentUser?.licenseNumber || '-'} /> : null}
        {currentRole === 'driver' ? <InfoRow label="Vehicle" value={`${currentUser?.vehicleModel || '-'} • ${currentUser?.vehiclePlateNumber || '-'}`} /> : null}
        {currentRole === 'student' ? <InfoRow label="LRN" value={currentUser?.lrn || '-'} /> : null}
        {currentRole === 'student' ? <InfoRow label="School" value={currentUser?.schoolName || '-'} /> : null}
        <AppButton
          label="Logout"
          variant="ghost"
          onPress={() => {
            logout();
            navigation.reset({ index: 0, routes: [{ name: 'Welcome' }] });
          }}
        />
      </SectionCard>
    </Screen>
  );
}
