import React, { useState } from 'react';
import { Alert } from 'react-native';
import AddressPinPicker from '../../components/AddressPinPicker';
import AppNavBar from '../../components/AppNavBar';
import AppButton from '../../components/AppButton';
import FormInput from '../../components/FormInput';
import HeaderBlock from '../../components/HeaderBlock';
import Screen from '../../components/Screen';
import SectionCard from '../../components/SectionCard';
import { useAppContext } from '../../context/AppContext';

export default function AddStudentScreen({ navigation }) {
  const { addStudent, refreshDashboard } = useAppContext();
  const [refreshing, setRefreshing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({
    studentName: '',
    email: '',
    mobileNumber: '',
    password: '',
    lrn: '',
    schoolName: '',
    gradeLevel: '',
    pickupAddress: '',
    pickupLatitude: '10.6765',
    pickupLongitude: '122.9509',
    dropoffAddress: '',
    dropoffLatitude: '10.6684',
    dropoffLongitude: '123.0198',
    emergencyContact: '',
    notes: '',
  });

  const updateField = (key, value) => setForm((current) => ({ ...current, [key]: value }));
  const updatePickup = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      pickupAddress: address,
      pickupLatitude: latitude,
      pickupLongitude: longitude,
    }));
  };
  const updateDropoff = ({ address, latitude, longitude }) => {
    setForm((current) => ({
      ...current,
      dropoffAddress: address,
      dropoffLatitude: latitude,
      dropoffLongitude: longitude,
    }));
  };

  const handleSave = async () => {
    if (!form.studentName.trim() || !form.lrn.trim()) {
      Alert.alert('Add child', 'Child name and LRN are required.');
      return;
    }

    setSaving(true);
    try {
      await addStudent({
        studentName: form.studentName.trim(),
        email: form.email.trim(),
        mobileNumber: form.mobileNumber.trim(),
        password: form.password,
        lrn: form.lrn.trim(),
        schoolName: form.schoolName.trim(),
        gradeLevel: form.gradeLevel.trim(),
        pickupAddress: form.pickupAddress.trim(),
        pickupLatitude: form.pickupLatitude,
        pickupLongitude: form.pickupLongitude,
        dropoffAddress: form.dropoffAddress.trim(),
        dropoffLatitude: form.dropoffLatitude,
        dropoffLongitude: form.dropoffLongitude,
        emergencyContact: form.emergencyContact.trim(),
        notes: form.notes.trim(),
      });

      navigation.goBack();
    } catch (error) {
      Alert.alert('Cannot add child', error.message || 'Please check the child details and try again.');
    } finally {
      setSaving(false);
    }
  };
  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await refreshDashboard();
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <Screen bottomBar={<AppNavBar navigation={navigation} active="students" />} refreshing={refreshing} onRefresh={handleRefresh}>
      <HeaderBlock
        eyebrow="New Child"
        title="Create the child account from the parent side."
        subtitle="In the real backend this should validate that the LRN is unique before saving."
      />
      <SectionCard icon="user-plus">
        <FormInput label="Child Name" value={form.studentName} onChangeText={(value) => updateField('studentName', value)} placeholder="Lia Villanueva" />
        <FormInput label="Child Email" value={form.email} onChangeText={(value) => updateField('email', value)} placeholder="child@example.com" keyboardType="email-address" />
        <FormInput label="Child Mobile Number" value={form.mobileNumber} onChangeText={(value) => updateField('mobileNumber', value)} placeholder="09XXXXXXXXX" keyboardType="phone-pad" />
        <FormInput label="Child Password" value={form.password} onChangeText={(value) => updateField('password', value)} placeholder="Temporary password" secureTextEntry />
        <FormInput label="LRN" value={form.lrn} onChangeText={(value) => updateField('lrn', value)} placeholder="112233445566" />
        <FormInput label="School Name" value={form.schoolName} onChangeText={(value) => updateField('schoolName', value)} placeholder="School name" />
        <FormInput label="Grade Level" value={form.gradeLevel} onChangeText={(value) => updateField('gradeLevel', value)} placeholder="Grade 8" />
        <AddressPinPicker label="Pickup Point" value={form.pickupAddress} latitude={form.pickupLatitude} longitude={form.pickupLongitude} onChange={updatePickup} />
        <AddressPinPicker label="Drop-off Point" value={form.dropoffAddress} latitude={form.dropoffLatitude} longitude={form.dropoffLongitude} onChange={updateDropoff} />
        <FormInput label="Emergency Contact" value={form.emergencyContact} onChangeText={(value) => updateField('emergencyContact', value)} placeholder="Parent mobile number" />
        <FormInput label="Medical Notes" value={form.notes} onChangeText={(value) => updateField('notes', value)} placeholder="Optional notes" multiline />
        <AppButton icon="save" label={saving ? 'Saving Child...' : 'Save Child Account'} disabled={saving} onPress={handleSave} />
      </SectionCard>
    </Screen>
  );
}
