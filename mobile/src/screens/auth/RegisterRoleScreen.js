import React from 'react';
import { Text } from 'react-native';
import AppButton from '../../components/AppButton';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';

export default function RegisterRoleScreen({ navigation }) {
  return (
    <Screen>
      <HeaderBlock
        eyebrow="Registration"
        title="Choose which account type you want to create."
        subtitle="Parents can create student accounts later. Students are not self-registered in this version because the parent manages them."
      />

      <SectionCard title="Parent account" subtitle="For guardians who book rides and manage student records.">
        <AppButton label="Register as Parent" onPress={() => navigation.navigate('RegisterParent')} />
      </SectionCard>

      <SectionCard title="Driver account" subtitle="For service drivers who upload credentials and wait for admin approval.">
        <AppButton label="Register as Driver" variant="secondary" onPress={() => navigation.navigate('RegisterDriver')} />
      </SectionCard>

      <SectionCard title="Student account policy">
        <Text>Student accounts are created by the parent inside the app, together with the learner reference number or LRN.</Text>
      </SectionCard>
    </Screen>
  );
}
